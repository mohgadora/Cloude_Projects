"""Expired session cleanup — runs as a background job."""
from datetime import datetime, timezone

from sqlalchemy.orm import Session

from models import UserSession


def purge_expired_sessions(db: Session) -> int:
    """Delete all sessions past their expiry. Returns count of deleted rows."""
    deleted = (
        db.query(UserSession)
        .filter(UserSession.expires_at <= datetime.now(timezone.utc))
        .delete(synchronize_session=False)
    )
    db.commit()
    return deleted
