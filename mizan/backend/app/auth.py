"""المصادقة والصلاحيات والأمان"""
import hashlib
import os
import secrets
from datetime import datetime, timedelta
from fastapi import HTTPException, Header, Depends
from typing import Optional
from .db import query_one, execute

# ─── كلمات المرور ──────────────────────────────────────
# كل مستخدم له salt عشوائي فريد مخزّن مع الهاش (format: hex_salt$hex_hash)
_ITERATIONS = 260_000

def hash_pw(pw: str, salt_hex: str = None) -> str:
    if salt_hex is None:
        salt_hex = os.urandom(16).hex()
    h = hashlib.pbkdf2_hmac("sha256", pw.encode(), bytes.fromhex(salt_hex), _ITERATIONS).hex()
    return f"{salt_hex}${h}"

def verify_pw(pw: str, stored: str) -> bool:
    # دعم الهاشات القديمة (بدون $) للتحويل التدريجي
    if "$" not in stored:
        return False
    salt_hex, _ = stored.split("$", 1)
    return secrets.compare_digest(hash_pw(pw, salt_hex), stored)

# ─── الجلسات ───────────────────────────────────────────
def make_token(user_id: int) -> str:
    token = secrets.token_urlsafe(40)
    expires = datetime.now() + timedelta(days=7)
    execute("INSERT INTO sessions (token, user_id, expires_at) VALUES (%s,%s,%s)",
            (token, user_id, expires))
    execute("UPDATE users SET last_login=NOW() WHERE id=%s", (user_id,))
    return token

def revoke_token(token: str):
    execute("DELETE FROM sessions WHERE token=%s", (token,))

def purge_expired_sessions():
    """حذف الجلسات المنتهية — يُستدعى دورياً"""
    execute("DELETE FROM sessions WHERE expires_at <= NOW()")

# ─── الحصول على المستخدم الحالي ────────────────────────
def current_user(authorization: Optional[str] = Header(None)) -> dict:
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(401, "مطلوب تسجيل الدخول")
    token = authorization.split(" ", 1)[1]
    row = query_one(
        """SELECT u.*, t.type AS tenant_type, t.name AS tenant_name, t.plan_id,
                  t.cases_used, t.docs_used, t.trial_ends_at,
                  p.code AS plan_code, p.name_ar AS plan_name,
                  p.cases_limit, p.docs_limit, p.seats_limit
           FROM sessions s
           JOIN users u   ON u.id = s.user_id
           JOIN tenants t ON t.id = u.tenant_id
           JOIN plans p   ON p.id = t.plan_id
           WHERE s.token=%s AND s.expires_at > NOW() AND u.active=1""", (token,))
    if not row:
        raise HTTPException(401, "انتهت الجلسة، سجّل الدخول مجدداً")
    row["_token"] = token
    return row

# ─── حراس الصلاحيات (RBAC) ─────────────────────────────
ROLE_RANK = {"assistant": 1, "lawyer": 2, "owner": 3}

def require_role(min_role: str):
    def dep(user=Depends(current_user)):
        if ROLE_RANK.get(user["role"], 0) < ROLE_RANK[min_role]:
            raise HTTPException(403, "ليس لديك صلاحية لهذا الإجراء")
        return user
    return dep

def can_use_tools(user: dict) -> bool:
    return user["role"] in ("owner", "lawyer")

# ─── حدود الاستخدام (Quota) ────────────────────────────
def check_and_increment_cases(user: dict):
    if user["cases_used"] >= user["cases_limit"]:
        raise HTTPException(402, f"بلغت حد باقتك ({user['cases_limit']} استشارة). يرجى الترقية.")
    execute("UPDATE tenants SET cases_used = cases_used + 1 WHERE id=%s", (user["tenant_id"],))

def check_docs_limit(user: dict):
    if user["docs_used"] >= user["docs_limit"]:
        raise HTTPException(402, f"بلغت حد المستندات ({user['docs_limit']}). يرجى الترقية.")
    execute("UPDATE tenants SET docs_used = docs_used + 1 WHERE id=%s", (user["tenant_id"],))
