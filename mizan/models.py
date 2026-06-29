from datetime import datetime
from sqlalchemy import (
    Column, Integer, String, Text, DateTime, ForeignKey, Boolean, JSON
)
from sqlalchemy.orm import relationship
from database import Base


class Tenant(Base):
    __tablename__ = "tenants"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    slug = Column(String(100), unique=True, nullable=False, index=True)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    users = relationship("User", back_populates="tenant", cascade="all, delete-orphan")
    documents = relationship("Document", back_populates="tenant", cascade="all, delete-orphan")
    templates = relationship("Template", back_populates="tenant", cascade="all, delete-orphan")


class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    tenant_id = Column(Integer, ForeignKey("tenants.id"), nullable=False, index=True)
    email = Column(String(255), nullable=False)
    full_name = Column(String(255))
    hashed_password = Column(String(255), nullable=False)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    tenant = relationship("Tenant", back_populates="users")
    sessions = relationship("UserSession", back_populates="user", cascade="all, delete-orphan")
    documents = relationship("Document", back_populates="owner")


class UserSession(Base):
    __tablename__ = "user_sessions"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False, index=True)
    token_jti = Column(String(255), unique=True, nullable=False, index=True)
    expires_at = Column(DateTime, nullable=False, index=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    user = relationship("User", back_populates="sessions")


class Template(Base):
    """قالب قانوني يحتوي على متغيرات قابلة للتعبئة."""
    __tablename__ = "templates"

    id = Column(Integer, primary_key=True, index=True)
    tenant_id = Column(Integer, ForeignKey("tenants.id"), nullable=False, index=True)
    name = Column(String(255), nullable=False)
    description = Column(Text)
    # JSON list of variable definitions: [{"key": "party_name", "label": "اسم الطرف", "type": "text"}]
    variables = Column(JSON, default=list)
    content = Column(Text, nullable=False)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    tenant = relationship("Tenant", back_populates="templates")
    documents = relationship("Document", back_populates="template")


class Document(Base):
    __tablename__ = "documents"

    id = Column(Integer, primary_key=True, index=True)
    tenant_id = Column(Integer, ForeignKey("tenants.id"), nullable=False, index=True)
    owner_id = Column(Integer, ForeignKey("users.id"), nullable=False, index=True)
    template_id = Column(Integer, ForeignKey("templates.id"), nullable=True)
    title = Column(String(255), nullable=False)
    content = Column(Text)
    # Filled variable values: {"party_name": "أحمد علي", ...}
    variable_values = Column(JSON, default=dict)
    ai_session_id = Column(String(255))
    is_deleted = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    tenant = relationship("Tenant", back_populates="documents")
    owner = relationship("User", back_populates="documents")
    template = relationship("Template", back_populates="documents")
