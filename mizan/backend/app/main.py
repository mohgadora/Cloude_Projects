"""
ميزان — منصة الذكاء القانوني (SaaS)
Backend رئيسي: حسابات + مؤسسات + صلاحيات + حدود + أدوات قانونية
"""
import json
import secrets
from pathlib import Path
from datetime import datetime, timedelta
from fastapi import FastAPI, HTTPException, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, Field
from typing import Optional, List, Dict, Any
from apscheduler.schedulers.background import BackgroundScheduler

from .db import query_one, query_all, execute
from .auth import (hash_pw, verify_pw, make_token, revoke_token, current_user,
                   require_role, can_use_tools, check_and_increment_cases, check_docs_limit,
                   purge_expired_sessions)
from . import services

app = FastAPI(title="Mizan Legal AI SaaS", version="3.0.0")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

# ── تنظيف الجلسات المنتهية كل ساعة ──────────────────────────────────────────
_scheduler = BackgroundScheduler()
_scheduler.add_job(purge_expired_sessions, "interval", hours=1)
_scheduler.start()

# ════ Models ════
class RegisterReq(BaseModel):
    name: str
    email: str
    password: str
    account_type: Optional[str] = "individual"   # individual | org
    org_name: Optional[str] = None

class LoginReq(BaseModel):
    email: str
    password: str

class PhoneReq(BaseModel):
    phone: str

class OTPReq(BaseModel):
    phone: str
    code: str

class ProfileReq(BaseModel):
    practice_areas: Optional[List[str]] = []
    experience: Optional[str] = None

class ConsultReq(BaseModel):
    question: str
    model: Optional[str] = None

class LegalSchemaReq(BaseModel):
    prompt: str

class LegalDocumentReq(BaseModel):
    schema_: Dict[str, Any] = Field(alias="schema")
    formValues: Dict[str, Any] = {}

class InspectDocumentReq(BaseModel):
    docText: str
    schema_: Optional[Dict[str, Any]] = Field(default=None, alias="schema")

class SaveDocumentReq(BaseModel):
    title: str
    kind: Optional[str] = "وثيقة ذكية"
    content: str
    schema_: Optional[Dict[str, Any]] = Field(default=None, alias="schema")
    formValues: Optional[Dict[str, Any]] = None
    doc_id: Optional[int] = None

class AnalyzeReq(BaseModel):
    case_description: str
    documents: Optional[str] = ""

class CaseReq(BaseModel):
    name: str
    plaintiff: Optional[str] = ""
    defendant: Optional[str] = ""
    represents: Optional[str] = ""
    description: Optional[str] = ""

class InviteReq(BaseModel):
    email: str
    role: Optional[str] = "lawyer"

# ════ Helpers ════
def user_public(u: dict) -> dict:
    return {
        "id": u["id"], "name": u["name"], "email": u["email"], "phone": u.get("phone"),
        "role": u["role"], "onboarded": u["onboarded"],
        "tenant_type": u["tenant_type"], "tenant_name": u["tenant_name"],
        "plan_code": u["plan_code"], "plan_name": u["plan_name"],
        "cases_used": u["cases_used"], "cases_limit": u["cases_limit"],
        "docs_used": u["docs_used"], "docs_limit": u["docs_limit"],
    }

def log(tenant_id, user_id, type_, title):
    execute("INSERT INTO activity (tenant_id,user_id,type,title) VALUES (%s,%s,%s,%s)",
            (tenant_id, user_id, type_, title[:240]))

# ════ Health ════
@app.get("/")
async def root(): return {"status": "Mizan Legal AI SaaS", "version": "3.0.0", "ui": "/ui"}

@app.get("/ui")
async def ui():
    index_path = Path(__file__).resolve().parents[2] / "frontend" / "index.html"
    if not index_path.exists():
        raise HTTPException(404, "frontend/index.html not found")
    return FileResponse(index_path, media_type="text/html; charset=utf-8")

@app.get("/health")
async def health():
    try:
        query_one("SELECT 1 AS ok")
        return {"status": "ok", "db": "connected"}
    except Exception as e:
        return {"status": "degraded", "db": str(e)}

# ════════ AUTH ════════
@app.post("/api/auth/register")
async def register(req: RegisterReq):
    if query_one("SELECT id FROM users WHERE email=%s", (req.email,)):
        raise HTTPException(400, "البريد مسجّل مسبقاً")
    is_org = req.account_type == "org"
    plan_id = 3 if is_org else 1  # team for org, trial for individual
    tenant_name = req.org_name if is_org and req.org_name else req.name
    trial_ends = datetime.now() + timedelta(days=3)
    tenant_id = execute(
        "INSERT INTO tenants (type,name,plan_id,trial_ends_at,usage_reset_at) VALUES (%s,%s,%s,%s,CURDATE())",
        ("org" if is_org else "individual", tenant_name, plan_id, trial_ends))
    uid = execute(
        "INSERT INTO users (tenant_id,name,email,password_hash,role) VALUES (%s,%s,%s,%s,'owner')",
        (tenant_id, req.name, req.email, hash_pw(req.password)))
    token = make_token(uid)
    u = current_user(f"Bearer {token}")
    return {"token": token, "user": user_public(u)}

@app.post("/api/auth/login")
async def login(req: LoginReq):
    u = query_one("SELECT * FROM users WHERE email=%s AND active=1", (req.email,))
    if not u or not verify_pw(req.password, u["password_hash"]):
        raise HTTPException(401, "البريد أو كلمة المرور غير صحيحة")
    token = make_token(u["id"])
    full = current_user(f"Bearer {token}")
    return {"token": token, "user": user_public(full)}

@app.post("/api/auth/logout")
async def logout(user=Depends(current_user)):
    revoke_token(user["_token"])
    return {"ok": True}

@app.get("/api/auth/me")
async def me(user=Depends(current_user)):
    return user_public(user)

# ════════ ONBOARDING ════════
_otp = {}

@app.post("/api/onboarding/profile")
async def save_profile(req: ProfileReq, user=Depends(current_user)):
    execute("UPDATE users SET practice_areas=%s, experience=%s WHERE id=%s",
            (json.dumps(req.practice_areas, ensure_ascii=False), req.experience, user["id"]))
    return {"ok": True}

@app.post("/api/onboarding/send-otp")
async def send_otp(req: PhoneReq, user=Depends(current_user)):
    code = "123456"  # API: اربط بمزود SMS فعلي (Unifonic/Twilio)
    _otp[req.phone] = code
    return {"sent": True, "message": "رمز التجربة: 123456"}

@app.post("/api/onboarding/verify-otp")
async def verify_otp(req: OTPReq, user=Depends(current_user)):
    if _otp.get(req.phone) != req.code:
        raise HTTPException(400, "رمز التحقق غير صحيح")
    execute("UPDATE users SET phone=%s, phone_verified=1, onboarded=1 WHERE id=%s",
            (req.phone, user["id"]))
    return {"verified": True}

@app.post("/api/onboarding/complete")
async def complete(user=Depends(current_user)):
    execute("UPDATE users SET onboarded=1 WHERE id=%s", (user["id"],))
    return {"ok": True}

# ════════ TOOLS (محمية + حدود) ════════
@app.post("/api/consult")
async def consult(req: ConsultReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "المساعد لا يملك صلاحية إنشاء استشارات")
    check_and_increment_cases(user)
    result = await services.legal_consult(req.question, req.model)
    log(user["tenant_id"], user["id"], "استشارة", req.question)
    return result

@app.post("/api/analyze-case")
async def analyze(req: AnalyzeReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "المساعد لا يملك صلاحية التحليل")
    check_and_increment_cases(user)
    result = await services.analyze_case(req.case_description, req.documents)
    log(user["tenant_id"], user["id"], "تحليل قضية", req.case_description)
    return result

@app.post("/api/draft-document")
async def draft(req: ConsultReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "المساعد لا يملك صلاحية الصياغة")
    check_docs_limit(user)
    result = await services.draft_document(req.question, req.model)
    doc_id = execute(
        "INSERT INTO documents (tenant_id,created_by,title,kind,content) VALUES (%s,%s,%s,%s,%s)",
        (user["tenant_id"], user["id"], req.question[:200], "وثيقة ذكية", result["document"]))
    log(user["tenant_id"], user["id"], "صياغة مستند", req.question)
    result["doc_id"] = doc_id
    return result

# ════════ SMART LEGAL DOCUMENTS AI ════════
@app.post("/api/ai/legal-schema")
async def ai_legal_schema(req: LegalSchemaReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "ليس لديك صلاحية إنشاء المستندات")
    schema = await services.generate_legal_schema(req.prompt)
    log(user["tenant_id"], user["id"], "تحليل مستند ذكي", req.prompt)
    return {"schema": schema}

@app.post("/api/ai/legal-document")
async def ai_legal_document(req: LegalDocumentReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "ليس لديك صلاحية إنشاء المستندات")
    check_docs_limit(user)
    document = await services.generate_legal_document(req.schema_, req.formValues)
    title = (req.schema_ or {}).get("documentTitle") or "مستند قانوني"
    kind = (req.schema_ or {}).get("documentCategory") or "وثيقة ذكية"
    doc_id = execute(
        "INSERT INTO documents (tenant_id,created_by,title,kind,content) VALUES (%s,%s,%s,%s,%s)",
        (user["tenant_id"], user["id"], title[:255], kind[:80], document)
    )
    log(user["tenant_id"], user["id"], "توليد مستند ذكي", title)
    return {"document": document, "doc_id": doc_id}

@app.post("/api/ai/inspect-document")
async def ai_inspect_document(req: InspectDocumentReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "ليس لديك صلاحية فحص المستندات")
    inspection = await services.inspect_legal_document(req.docText, req.schema_)
    log(user["tenant_id"], user["id"], "فحص مستند", (req.schema_ or {}).get("documentTitle", "مستند"))
    return {"inspection": inspection}

# ════════ CASES ════════
@app.get("/api/cases")
async def list_cases(user=Depends(current_user)):
    rows = query_all("SELECT * FROM cases WHERE tenant_id=%s ORDER BY id DESC", (user["tenant_id"],))
    return {"cases": rows}

@app.post("/api/cases")
async def create_case(req: CaseReq, user=Depends(current_user)):
    if not can_use_tools(user):
        raise HTTPException(403, "ليس لديك صلاحية إنشاء قضية")
    cid = execute(
        """INSERT INTO cases (tenant_id,created_by,name,plaintiff,defendant,represents,description)
           VALUES (%s,%s,%s,%s,%s,%s,%s)""",
        (user["tenant_id"], user["id"], req.name, req.plaintiff, req.defendant, req.represents, req.description))
    log(user["tenant_id"], user["id"], "قضية جديدة", req.name)
    return {"id": cid, "ok": True}

# ════════ DOCUMENTS ════════
@app.get("/api/documents")
async def list_docs(user=Depends(current_user)):
    rows = query_all("SELECT id,title,kind,created_at FROM documents WHERE tenant_id=%s ORDER BY id DESC", (user["tenant_id"],))
    return {"documents": rows}

@app.get("/api/documents/{doc_id}")
async def get_doc(doc_id: int, user=Depends(current_user)):
    row = query_one("SELECT id,title,kind,content,created_at FROM documents WHERE id=%s AND tenant_id=%s", (doc_id, user["tenant_id"]))
    if not row:
        raise HTTPException(404, "المستند غير موجود")
    return {"document": row}

@app.post("/api/documents")
async def save_doc(req: SaveDocumentReq, user=Depends(current_user)):
    if req.doc_id:
        existing = query_one("SELECT id FROM documents WHERE id=%s AND tenant_id=%s", (req.doc_id, user["tenant_id"]))
        if existing:
            execute("UPDATE documents SET title=%s,kind=%s,content=%s WHERE id=%s AND tenant_id=%s",
                    (req.title[:255], (req.kind or "وثيقة ذكية")[:80], req.content, req.doc_id, user["tenant_id"]))
            return {"ok": True, "doc_id": req.doc_id}
    check_docs_limit(user)
    doc_id = execute(
        "INSERT INTO documents (tenant_id,created_by,title,kind,content) VALUES (%s,%s,%s,%s,%s)",
        (user["tenant_id"], user["id"], req.title[:255], (req.kind or "وثيقة ذكية")[:80], req.content)
    )
    log(user["tenant_id"], user["id"], "حفظ مستند", req.title)
    return {"ok": True, "doc_id": doc_id}

# ════════ ACTIVITY ════════
@app.get("/api/activity")
async def activity(user=Depends(current_user)):
    rows = query_all("SELECT type,title,created_at FROM activity WHERE tenant_id=%s ORDER BY id DESC LIMIT 50", (user["tenant_id"],))
    return {"activity": rows}

# ════════ TEAM (للمؤسسات — المالك فقط) ════════
@app.get("/api/team")
async def team(user=Depends(require_role("owner"))):
    rows = query_all("SELECT id,name,email,role,active,last_login FROM users WHERE tenant_id=%s", (user["tenant_id"],))
    return {"members": rows, "seats_limit": user["seats_limit"]}

@app.post("/api/team/invite")
async def invite(req: InviteReq, user=Depends(require_role("owner"))):
    count = query_one("SELECT COUNT(*) AS n FROM users WHERE tenant_id=%s", (user["tenant_id"],))["n"]
    if count >= user["seats_limit"]:
        raise HTTPException(402, f"بلغت حد المقاعد ({user['seats_limit']}). يرجى الترقية.")
    token = secrets.token_urlsafe(24)
    execute("INSERT INTO invites (tenant_id,email,role,token) VALUES (%s,%s,%s,%s)",
            (user["tenant_id"], req.email, req.role, token))
    # API: أرسل بريد الدعوة هنا
    return {"ok": True, "invite_link": f"/accept-invite?token={token}"}

@app.post("/api/team/{member_id}/deactivate")
async def deactivate(member_id: int, user=Depends(require_role("owner"))):
    m = query_one("SELECT * FROM users WHERE id=%s AND tenant_id=%s", (member_id, user["tenant_id"]))
    if not m:
        raise HTTPException(404, "العضو غير موجود")
    if m["role"] == "owner":
        raise HTTPException(400, "لا يمكن تعطيل المالك")
    execute("UPDATE users SET active=0 WHERE id=%s", (member_id,))
    return {"ok": True}

# ════════ EXPORT ════════
@app.get("/api/documents/{doc_id}/export/word")
async def export_word(doc_id: int, user=Depends(current_user)):
    from fastapi.responses import Response
    import io
    try:
        from docx import Document as DocxDoc
        from docx.shared import Pt
    except ImportError:
        raise HTTPException(500, "مكتبة python-docx غير مثبّتة")
    row = query_one("SELECT * FROM documents WHERE id=%s AND tenant_id=%s", (doc_id, user["tenant_id"]))
    if not row:
        raise HTTPException(404, "المستند غير موجود")
    doc = DocxDoc()
    doc.add_heading(row["title"], level=1)
    for line in (row["content"] or "").split("\n"):
        doc.add_paragraph(line)
    buf = io.BytesIO()
    doc.save(buf)
    buf.seek(0)
    fname = row["title"].replace(" ", "_")[:80] + ".docx"
    return Response(
        content=buf.read(),
        media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        headers={"Content-Disposition": f'attachment; filename="{fname}"'},
    )

# ════════ PLANS ════════
@app.get("/api/plans")
async def plans():
    return {"plans": query_all("SELECT * FROM plans ORDER BY price_month")}
