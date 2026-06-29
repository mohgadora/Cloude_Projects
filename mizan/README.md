# ميزان — منصة الذكاء القانوني

هذه نسخة معدلة من المشروع بحيث لا يتم استدعاء مزود الذكاء الاصطناعي من المتصفح. الواجهة تستدعي الباكند، والباكند يتصل بـ RAGFlow API بشكل آمن.

## أهم التعديلات

- استبدال استدعاء Claude المباشر من `frontend/index.html` باستدعاءات:
  - `/api/ai/legal-schema`
  - `/api/ai/legal-document`
  - `/api/ai/inspect-document`
- إضافة ربط RAGFlow عبر OpenAI-compatible endpoint:
  - `/api/v1/openai/{chat_id}/chat/completions`
  - مع fallback للمسار القديم `/api/v1/chats_openai/{chat_id}/chat/completions`
- إضافة fallback تجريبي `AI_FALLBACK_ENABLED=true` حتى لا تتوقف الواجهة إذا لم تضبط مفتاح RAGFlow بعد.
- استخدام `index (1).html` كواجهة أساسية لأنه يحتوي وحدة المستندات الذكية والديناميكية.

## التشغيل

### 1) إعداد قاعدة البيانات

أنشئ قاعدة MySQL ثم نفّذ:

```bash
mysql -u root -p < backend/schema.sql
```

### 2) إعداد البيئة

```bash
cd backend
cp .env.example .env
nano .env
```

عدّل هذه القيم:

```env
RAGFLOW_URL=http://213.136.68.39:8880
RAGFLOW_CHAT_ID=b1f2f15691f911ef81180242ac120003
RAGFLOW_API_KEY=YOUR_RAGFLOW_API_KEY
DB_PASS=YOUR_MYSQL_ROOT_PASSWORD
```

### 3) تثبيت وتشغيل الباكند

```bash
cd backend
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```

### 4) فتح الواجهة

افتح:

```text
http://YOUR_SERVER_IP:8000/ui
```

لا تفتح `index.html` مباشرة من الجهاز؛ افتحه عبر `/ui` حتى تعمل طلبات `/api/...` على نفس السيرفر.

## ملاحظة مهمة

إذا ظهر خطأ في RAGFlow، سيستخدم التطبيق mock response طالما:

```env
AI_FALLBACK_ENABLED=true
```

لتعطيل fallback واكتشاف أخطاء RAGFlow مباشرة:

```env
AI_FALLBACK_ENABLED=false
```
