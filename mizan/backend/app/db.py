import os
import pymysql
from pymysql.cursors import DictCursor
from contextlib import contextmanager
from dotenv import load_dotenv
load_dotenv()

RAGFLOW_API_KEY = os.getenv("RAGFLOW_API_KEY")
RAGFLOW_URL     = os.getenv("RAGFLOW_URL", "http://213.136.68.39:8880")
RAGFLOW_CHAT_ID = os.getenv("RAGFLOW_CHAT_ID", "b1f2f15691f911ef81180242ac120003")
OLLAMA_URL      = os.getenv("OLLAMA_URL", "http://localhost:11434")
DEFAULT_KB_ID   = os.getenv("DEFAULT_KB_ID")
DEFAULT_MODEL   = os.getenv("DEFAULT_MODEL", "qwen3:8b")
AI_FALLBACK_ENABLED = os.getenv("AI_FALLBACK_ENABLED", "true").lower() in ("1", "true", "yes", "on")
SECRET_KEY      = os.getenv("SECRET_KEY", "change-me-in-production")

DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("DB_PORT", "3316"))
DB_USER = os.getenv("DB_USER", "root")
DB_PASS = os.getenv("DB_PASS", "")
DB_NAME = os.getenv("DB_NAME", "mizan")

@contextmanager
def get_db():
    conn = pymysql.connect(
        host=DB_HOST, port=DB_PORT, user=DB_USER, password=DB_PASS,
        database=DB_NAME, charset="utf8mb4", use_unicode=True,
        cursorclass=DictCursor, autocommit=True,
        init_command="SET NAMES utf8mb4"
    )
    try:
        yield conn
    finally:
        conn.close()

def query_one(sql, params=None):
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.fetchone()

def query_all(sql, params=None):
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.fetchall()

def execute(sql, params=None):
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.lastrowid
