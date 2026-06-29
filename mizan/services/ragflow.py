"""RAGFlow chat integration."""
import httpx
from database import settings


async def chat(message: str, session_id: str | None = None) -> dict:
    """
    Send a message to the RAGFlow chat assistant.
    Returns {"answer": str, "session_id": str}.
    """
    base = settings.ragflow_base_url.rstrip("/")
    headers = {
        "Authorization": f"Bearer {settings.ragflow_api_key}",
        "Content-Type": "application/json",
    }
    payload: dict = {
        "question": message,
        "stream": False,
    }
    if session_id:
        payload["session_id"] = session_id

    url = f"{base}/api/v1/chats/{settings.ragflow_chat_id}/completions"

    async with httpx.AsyncClient(timeout=60.0) as client:
        resp = await client.post(url, json=payload, headers=headers)
        resp.raise_for_status()
        data = resp.json()

    return {
        "answer": data.get("data", {}).get("answer", ""),
        "session_id": data.get("data", {}).get("session_id", session_id or ""),
    }
