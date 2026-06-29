from datetime import datetime, timedelta, timezone

from apscheduler.schedulers.background import BackgroundScheduler
from fastapi import Depends, FastAPI, HTTPException, Response, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.requests import Request
from fastapi.responses import HTMLResponse
from sqlalchemy.orm import Session

from auth import (
    create_access_token,
    get_current_user,
    hash_password,
    verify_password,
)
from database import Base, SessionLocal, engine, get_db, settings
from models import Document, Template, Tenant, User, UserSession
from schemas import (
    ChatRequest,
    ChatResponse,
    DocumentCreate,
    DocumentRead,
    DocumentUpdate,
    LoginRequest,
    TemplateCreate,
    TemplateRead,
    TenantCreate,
    TenantRead,
    TokenResponse,
    UserCreate,
    UserRead,
)
from services.document import (
    build_document_content,
    export_to_word,
    get_document_or_404,
)
from services.ragflow import chat as ragflow_chat
from services.session import purge_expired_sessions

# ── App setup ─────────────────────────────────────────────────────────────────

Base.metadata.create_all(bind=engine)

app = FastAPI(title="ميزان - نظام الذكاء الاصطناعي القانوني", version="1.0.0")

app.mount("/static", StaticFiles(directory="static"), name="static")
jinja = Jinja2Templates(directory="templates")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ── Background scheduler: purge expired sessions every hour ───────────────────

def _purge_job() -> None:
    db = SessionLocal()
    try:
        count = purge_expired_sessions(db)
        if count:
            print(f"[scheduler] purged {count} expired sessions")
    finally:
        db.close()


scheduler = BackgroundScheduler()
scheduler.add_job(_purge_job, "interval", hours=1)
scheduler.start()


# ── Tenants ───────────────────────────────────────────────────────────────────

@app.post("/api/tenants", response_model=TenantRead, status_code=status.HTTP_201_CREATED)
def create_tenant(body: TenantCreate, db: Session = Depends(get_db)):
    if db.query(Tenant).filter(Tenant.slug == body.slug).first():
        raise HTTPException(status_code=400, detail="المنظمة موجودة مسبقاً")
    tenant = Tenant(name=body.name, slug=body.slug)
    db.add(tenant)
    db.commit()
    db.refresh(tenant)
    return tenant


# ── Auth ──────────────────────────────────────────────────────────────────────

@app.post("/api/auth/register", response_model=UserRead, status_code=status.HTTP_201_CREATED)
def register(body: UserCreate, db: Session = Depends(get_db)):
    tenant = db.query(Tenant).filter(Tenant.slug == body.tenant_slug, Tenant.is_active == True).first()
    if not tenant:
        raise HTTPException(status_code=404, detail="المنظمة غير موجودة")
    if db.query(User).filter(User.email == body.email, User.tenant_id == tenant.id).first():
        raise HTTPException(status_code=400, detail="البريد الإلكتروني مسجّل مسبقاً")
    user = User(
        tenant_id=tenant.id,
        email=body.email,
        full_name=body.full_name,
        hashed_password=hash_password(body.password),
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user


@app.post("/api/auth/login", response_model=TokenResponse)
def login(body: LoginRequest, db: Session = Depends(get_db)):
    tenant = db.query(Tenant).filter(Tenant.slug == body.tenant_slug, Tenant.is_active == True).first()
    if not tenant:
        raise HTTPException(status_code=404, detail="المنظمة غير موجودة")
    user = db.query(User).filter(User.email == body.email, User.tenant_id == tenant.id, User.is_active == True).first()
    if not user or not verify_password(body.password, user.hashed_password):
        raise HTTPException(status_code=401, detail="بيانات الدخول غير صحيحة")

    token, jti = create_access_token(user.id, tenant.id)
    expires_at = datetime.now(timezone.utc) + timedelta(minutes=settings.access_token_expire_minutes)
    db.add(UserSession(user_id=user.id, token_jti=jti, expires_at=expires_at))
    db.commit()
    return TokenResponse(access_token=token)


@app.post("/api/auth/logout", status_code=status.HTTP_204_NO_CONTENT)
def logout(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    # Invalidate all active sessions for this user
    db.query(UserSession).filter(
        UserSession.user_id == current_user.id,
        UserSession.expires_at > datetime.now(timezone.utc),
    ).delete(synchronize_session=False)
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@app.get("/api/auth/me", response_model=UserRead)
def me(current_user: User = Depends(get_current_user)):
    return current_user


# ── Templates ─────────────────────────────────────────────────────────────────

@app.post("/api/templates", response_model=TemplateRead, status_code=status.HTTP_201_CREATED)
def create_template(
    body: TemplateCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    tmpl = Template(
        tenant_id=current_user.tenant_id,
        name=body.name,
        description=body.description,
        variables=[v.model_dump() for v in body.variables],
        content=body.content,
    )
    db.add(tmpl)
    db.commit()
    db.refresh(tmpl)
    return tmpl


@app.get("/api/templates", response_model=list[TemplateRead])
def list_templates(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    return (
        db.query(Template)
        .filter(Template.tenant_id == current_user.tenant_id, Template.is_active == True)
        .all()
    )


@app.get("/api/templates/{template_id}", response_model=TemplateRead)
def get_template(
    template_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    tmpl = db.query(Template).filter(
        Template.id == template_id,
        Template.tenant_id == current_user.tenant_id,
        Template.is_active == True,
    ).first()
    if not tmpl:
        raise HTTPException(status_code=404, detail="القالب غير موجود")
    return tmpl


@app.delete("/api/templates/{template_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_template(
    template_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    tmpl = db.query(Template).filter(
        Template.id == template_id,
        Template.tenant_id == current_user.tenant_id,
    ).first()
    if not tmpl:
        raise HTTPException(status_code=404, detail="القالب غير موجود")
    tmpl.is_active = False
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


# ── Documents ─────────────────────────────────────────────────────────────────

@app.post("/api/documents", response_model=DocumentRead, status_code=status.HTTP_201_CREATED)
def create_document(
    body: DocumentCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    content = None
    if body.template_id:
        tmpl = db.query(Template).filter(
            Template.id == body.template_id,
            Template.tenant_id == current_user.tenant_id,
            Template.is_active == True,
        ).first()
        if not tmpl:
            raise HTTPException(status_code=404, detail="القالب غير موجود")
        content = build_document_content(tmpl, body.variable_values)

    doc = Document(
        tenant_id=current_user.tenant_id,
        owner_id=current_user.id,
        template_id=body.template_id,
        title=body.title,
        content=content,
        variable_values=body.variable_values,
    )
    db.add(doc)
    db.commit()
    db.refresh(doc)
    return doc


@app.get("/api/documents", response_model=list[DocumentRead])
def list_documents(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    return (
        db.query(Document)
        .filter(
            Document.tenant_id == current_user.tenant_id,
            Document.owner_id == current_user.id,
            Document.is_deleted == False,
        )
        .order_by(Document.updated_at.desc())
        .all()
    )


@app.get("/api/documents/{doc_id}", response_model=DocumentRead)
def get_document(
    doc_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    return get_document_or_404(doc_id, current_user.tenant_id, db)


@app.patch("/api/documents/{doc_id}", response_model=DocumentRead)
def update_document(
    doc_id: int,
    body: DocumentUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    doc = get_document_or_404(doc_id, current_user.tenant_id, db)
    if body.title is not None:
        doc.title = body.title
    if body.content is not None:
        doc.content = body.content
    if body.variable_values is not None:
        doc.variable_values = body.variable_values
    db.commit()
    db.refresh(doc)
    return doc


@app.delete("/api/documents/{doc_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_document(
    doc_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    doc = get_document_or_404(doc_id, current_user.tenant_id, db)
    doc.is_deleted = True
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@app.get("/api/export/word/{doc_id}")
def export_document_word(
    doc_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    doc = get_document_or_404(doc_id, current_user.tenant_id, db)
    content = doc.content or ""
    word_bytes = export_to_word(doc.title, content)
    filename = f"{doc.title}.docx".replace(" ", "_")
    return Response(
        content=word_bytes,
        media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


# ── AI Chat ───────────────────────────────────────────────────────────────────

@app.post("/api/chat", response_model=ChatResponse)
async def chat_endpoint(
    body: ChatRequest,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    session_id: str | None = None

    if body.document_id:
        doc = get_document_or_404(body.document_id, current_user.tenant_id, db)
        session_id = doc.ai_session_id

    try:
        result = await ragflow_chat(body.message, session_id)
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"خطأ في محرك الذكاء الاصطناعي: {exc}")

    # Persist session_id on the document so follow-up messages stay in context
    if body.document_id and result["session_id"] != session_id:
        doc = get_document_or_404(body.document_id, current_user.tenant_id, db)
        doc.ai_session_id = result["session_id"]
        db.commit()

    return ChatResponse(answer=result["answer"], session_id=result["session_id"])


# ── UI Pages ──────────────────────────────────────────────────────────────────

@app.get("/", response_class=HTMLResponse)
def page_login(request: Request):
    return jinja.TemplateResponse("login.html", {"request": request})


@app.get("/dashboard", response_class=HTMLResponse)
def page_dashboard(request: Request):
    return jinja.TemplateResponse("dashboard.html", {"request": request})


# ── Health ────────────────────────────────────────────────────────────────────

@app.get("/health")
def health():
    return {"status": "ok"}
