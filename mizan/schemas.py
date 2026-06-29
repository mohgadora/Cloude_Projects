from datetime import datetime
from typing import Any
from pydantic import BaseModel, EmailStr, field_validator


# ── Auth ──────────────────────────────────────────────────────────────────────

class UserCreate(BaseModel):
    email: EmailStr
    full_name: str
    password: str
    tenant_slug: str

    @field_validator("password")
    @classmethod
    def password_min_length(cls, v: str) -> str:
        if len(v) < 8:
            raise ValueError("كلمة المرور يجب أن تكون 8 أحرف على الأقل")
        return v


class UserRead(BaseModel):
    model_config = {"from_attributes": True}

    id: int
    email: str
    full_name: str | None
    tenant_id: int
    is_active: bool
    created_at: datetime


class LoginRequest(BaseModel):
    email: EmailStr
    password: str
    tenant_slug: str


class TokenResponse(BaseModel):
    access_token: str
    token_type: str = "bearer"


# ── Tenant ────────────────────────────────────────────────────────────────────

class TenantCreate(BaseModel):
    name: str
    slug: str


class TenantRead(BaseModel):
    model_config = {"from_attributes": True}

    id: int
    name: str
    slug: str
    is_active: bool
    created_at: datetime


# ── Template ──────────────────────────────────────────────────────────────────

class TemplateVariable(BaseModel):
    key: str
    label: str
    type: str = "text"  # text | date | number | select


class TemplateCreate(BaseModel):
    name: str
    description: str | None = None
    variables: list[TemplateVariable] = []
    content: str


class TemplateRead(BaseModel):
    model_config = {"from_attributes": True}

    id: int
    tenant_id: int
    name: str
    description: str | None
    variables: list[dict]
    content: str
    is_active: bool
    created_at: datetime
    updated_at: datetime


# ── Document ──────────────────────────────────────────────────────────────────

class DocumentCreate(BaseModel):
    title: str
    template_id: int | None = None
    variable_values: dict[str, Any] = {}


class DocumentUpdate(BaseModel):
    title: str | None = None
    content: str | None = None
    variable_values: dict[str, Any] | None = None


class DocumentRead(BaseModel):
    model_config = {"from_attributes": True}

    id: int
    tenant_id: int
    owner_id: int
    template_id: int | None
    title: str
    content: str | None
    variable_values: dict
    ai_session_id: str | None
    created_at: datetime
    updated_at: datetime


# ── AI Chat ───────────────────────────────────────────────────────────────────

class ChatRequest(BaseModel):
    message: str
    document_id: int | None = None


class ChatResponse(BaseModel):
    answer: str
    session_id: str
