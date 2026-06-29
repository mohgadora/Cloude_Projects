"""خدمات RAGFlow والذكاء الاصطناعي لمنصة ميزان."""
import json
import re
import httpx
import asyncio
from typing import Any, Dict, List, Optional
from .db import (
    RAGFLOW_API_KEY,
    RAGFLOW_URL,
    RAGFLOW_CHAT_ID,
    OLLAMA_URL,
    DEFAULT_KB_ID,
    DEFAULT_MODEL,
    AI_FALLBACK_ENABLED,
)


# ═══════════════════════════════════════════════════════════════
# أدوات عامة
# ═══════════════════════════════════════════════════════════════

def extract_json(text: str) -> Dict[str, Any]:
    """استخراج أول JSON object صالح من رد النموذج."""
    if not text:
        raise ValueError("AI response is empty")
    text = text.strip()
    # إزالة أسوار markdown إن وُجدت
    text = re.sub(r"^```(?:json)?", "", text, flags=re.I).strip()
    text = re.sub(r"```$", "", text).strip()
    try:
        return json.loads(text)
    except Exception:
        pass
    match = re.search(r"\{[\s\S]*\}", text)
    if not match:
        raise ValueError("AI response did not contain JSON")
    return json.loads(match.group(0))


def _normalize_field(field: Dict[str, Any]) -> Dict[str, Any]:
    allowed = {"text", "number", "date", "textarea", "select", "checkbox", "radio", "email", "phone", "currency", "file", "repeater"}
    ftype = str(field.get("type") or "text").strip()
    if ftype not in allowed:
        ftype = "text"
    name = re.sub(r"[^a-zA-Z0-9_]", "", str(field.get("name") or "field")) or "field"
    out = {
        "name": name,
        "label": str(field.get("label") or name),
        "type": ftype,
        "required": bool(field.get("required", False)),
        "value": field.get("value", None),
        "placeholder": str(field.get("placeholder") or ""),
        "options": field.get("options") if isinstance(field.get("options"), list) else [],
        "fields": [],
    }
    if ftype == "repeater":
        sub_fields = field.get("fields") if isinstance(field.get("fields"), list) else []
        out["fields"] = [_normalize_field(sf) for sf in sub_fields]
        if not out["fields"]:
            out["fields"] = [
                {"name": "item", "label": "البند", "type": "text", "required": True, "value": None, "placeholder": "", "options": [], "fields": []}
            ]
    return out


def normalize_schema(schema: Dict[str, Any]) -> Dict[str, Any]:
    fields = schema.get("fields") if isinstance(schema.get("fields"), list) else []
    normalized = {
        "documentType": str(schema.get("documentType") or "legalDocument"),
        "documentTitle": str(schema.get("documentTitle") or "مستند قانوني"),
        "documentCategory": str(schema.get("documentCategory") or "other"),
        "confidence": float(schema.get("confidence") or 0.75),
        "jurisdiction": str(schema.get("jurisdiction") or "غير محدد"),
        "detectedParties": schema.get("detectedParties") if isinstance(schema.get("detectedParties"), list) else [],
        "summary": str(schema.get("summary") or "مستند قانوني بناءً على طلب المستخدم"),
        "extractedData": schema.get("extractedData") if isinstance(schema.get("extractedData"), dict) else {},
        "missingRequiredFields": schema.get("missingRequiredFields") if isinstance(schema.get("missingRequiredFields"), list) else [],
        "fields": [_normalize_field(f) for f in fields],
    }
    if not normalized["fields"]:
        normalized["fields"] = mock_legal_schema("مستند قانوني")["fields"]
    return normalized


# ═══════════════════════════════════════════════════════════════
# RAGFlow Chat Completions
# ═══════════════════════════════════════════════════════════════

async def call_ragflow_chat(messages: List[Dict[str, str]], timeout: int = 120) -> str:
    """استدعاء مساعد RAGFlow باستخدام OpenAI-compatible endpoint."""
    if not RAGFLOW_URL or not RAGFLOW_CHAT_ID or not RAGFLOW_API_KEY:
        raise RuntimeError("RAGFlow settings are incomplete. Check RAGFLOW_URL, RAGFLOW_CHAT_ID, RAGFLOW_API_KEY")

    base = RAGFLOW_URL.rstrip("/")
    endpoints = [
        f"{base}/api/v1/openai/{RAGFLOW_CHAT_ID}/chat/completions",
        # مسار قديم لبعض الإصدارات
        f"{base}/api/v1/chats_openai/{RAGFLOW_CHAT_ID}/chat/completions",
    ]
    last_error = None
    async with httpx.AsyncClient(timeout=timeout) as c:
        for url in endpoints:
            try:
                r = await c.post(
                    url,
                    headers={"Content-Type": "application/json", "Authorization": f"Bearer {RAGFLOW_API_KEY}"},
                    json={"model": "model", "stream": False, "messages": messages},
                )
                text = r.text
                if r.status_code == 404:
                    last_error = f"404 on {url}: {text[:300]}"
                    continue
                r.raise_for_status()
                data = r.json()
                return data.get("choices", [{}])[0].get("message", {}).get("content", "")
            except Exception as e:
                last_error = str(e)
                continue
    raise RuntimeError(f"RAGFlow request failed: {last_error}")


# ═══════════════════════════════════════════════════════════════
# Mock/Fallback — يضمن أن التطبيق يعمل حتى قبل ضبط مفاتيح RAGFlow
# ═══════════════════════════════════════════════════════════════

def mock_legal_schema(prompt: str) -> Dict[str, Any]:
    p = prompt.lower()
    title = "مستند قانوني"
    category = "other"
    doc_type = "legalDocument"
    fields = []

    def base_fields():
        return [
            {"name": "jurisdiction", "label": "الدولة / القانون الحاكم", "type": "text", "required": True, "value": None, "placeholder": "مثال: السعودية، سلطنة عمان، الإمارات"},
            {"name": "effectiveDate", "label": "تاريخ السريان", "type": "date", "required": True, "value": None},
            {"name": "extraTerms", "label": "شروط أو ملاحظات إضافية", "type": "textarea", "required": False, "value": None, "placeholder": "أي شروط خاصة تريد إضافتها"},
        ]

    if "عدم إفصاح" in prompt or "nda" in p or "سرية" in prompt:
        title, category, doc_type = "اتفاقية عدم إفصاح", "agreement", "nonDisclosureAgreement"
        fields = [
            {"name": "firstParty", "label": "الطرف الأول", "type": "text", "required": True, "value": None, "placeholder": "اسم الطرف الأول"},
            {"name": "secondParty", "label": "الطرف الثاني", "type": "text", "required": True, "value": None, "placeholder": "اسم الطرف الثاني"},
            {"name": "confidentialInformation", "label": "نوع المعلومات السرية", "type": "textarea", "required": True, "value": None, "placeholder": "بيانات العملاء، الأسعار، التصاميم، الأكواد..."},
            {"name": "agreementDuration", "label": "مدة الاتفاقية", "type": "text", "required": True, "value": None, "placeholder": "مثال: سنتين"},
            {"name": "disputeResolution", "label": "آلية حل النزاعات", "type": "select", "required": False, "options": ["المحاكم المختصة", "التحكيم", "التسوية الودية أولًا"]},
        ] + base_fields()
    elif "توريد" in prompt or "تزويد" in prompt:
        title, category, doc_type = "عقد توريد", "contract", "supplyContract"
        fields = [
            {"name": "supplier", "label": "اسم المورد", "type": "text", "required": True},
            {"name": "buyer", "label": "اسم المشتري", "type": "text", "required": True},
            {"name": "products", "label": "المنتجات أو الخدمات", "type": "repeater", "required": True, "fields": [
                {"name": "productName", "label": "اسم المنتج", "type": "text", "required": True},
                {"name": "quantity", "label": "الكمية", "type": "number", "required": True},
                {"name": "specifications", "label": "المواصفات", "type": "textarea", "required": False},
                {"name": "price", "label": "السعر", "type": "currency", "required": False},
            ]},
            {"name": "deliverySchedule", "label": "جدول التسليم", "type": "textarea", "required": True},
            {"name": "warranty", "label": "الضمان", "type": "textarea", "required": False},
        ] + base_fields()
    elif "لائحة" in prompt or "سياسة" in prompt:
        title, category, doc_type = "لائحة داخلية", "policy", "internalPolicy"
        fields = [
            {"name": "organizationName", "label": "اسم المنشأة", "type": "text", "required": True},
            {"name": "scope", "label": "نطاق تطبيق اللائحة", "type": "textarea", "required": True},
            {"name": "workHours", "label": "ساعات العمل", "type": "textarea", "required": False},
            {"name": "violations", "label": "المخالفات والجزاءات", "type": "textarea", "required": False},
        ] + base_fields()
    else:
        fields = [
            {"name": "documentPurpose", "label": "الغرض من المستند", "type": "textarea", "required": True, "value": prompt, "placeholder": "اشرح الغرض من المستند"},
            {"name": "parties", "label": "الأطراف", "type": "repeater", "required": True, "fields": [
                {"name": "partyName", "label": "اسم الطرف", "type": "text", "required": True},
                {"name": "partyRole", "label": "صفة الطرف", "type": "text", "required": False},
            ]},
            {"name": "obligations", "label": "الالتزامات الأساسية", "type": "textarea", "required": True},
        ] + base_fields()

    return normalize_schema({
        "documentType": doc_type,
        "documentTitle": title,
        "documentCategory": category,
        "confidence": 0.72,
        "jurisdiction": "غير محدد",
        "detectedParties": [],
        "summary": f"صياغة {title} بناءً على وصف المستخدم.",
        "extractedData": {},
        "missingRequiredFields": [f.get("name") for f in fields if f.get("required")],
        "fields": fields,
    })


def mock_document(schema: Dict[str, Any], form_values: Dict[str, Any]) -> str:
    title = schema.get("documentTitle", "مستند قانوني")
    lines = [
        title,
        "",
        "تمهيد",
        f"بناءً على البيانات المدخلة، تم إعداد هذا المستند من نوع: {title}.",
        "",
        "بيانات المستند",
    ]
    for key, value in (form_values or {}).items():
        if isinstance(value, list):
            lines.append(f"- {key}:")
            for i, item in enumerate(value, 1):
                lines.append(f"  {i}. {json.dumps(item, ensure_ascii=False)}")
        else:
            lines.append(f"- {key}: {value}")
    lines += [
        "",
        "الأحكام العامة",
        "يلتزم الأطراف بتنفيذ ما ورد في هذا المستند بحسن نية ووفق الأنظمة والقوانين ذات الصلة.",
        "",
        "حل النزاعات",
        "تُحل أي نزاعات تنشأ عن هذا المستند بالطرق الودية أولًا، ثم لدى الجهة المختصة وفق القانون الحاكم.",
        "",
        "التوقيعات",
        "الطرف الأول: ____________________",
        "الطرف الثاني: ____________________",
        "",
        "ملاحظة: هذا المستند صياغة أولية ويُنصح بمراجعة متخصص قانوني قبل الاعتماد الرسمي.",
    ]
    return "\n".join(lines)


def mock_inspection() -> Dict[str, Any]:
    return {
        "missingData": ["تأكد من إدخال القانون الحاكم وتواريخ السريان والتوقيع."],
        "weakClauses": [],
        "conflictingClauses": [],
        "risks": ["الصياغة تحتاج مراجعة مختص قبل الاعتماد الرسمي."],
        "improvements": ["أضف آلية واضحة لحل النزاعات، وبيانات الأطراف الرسمية، وشروط الإنهاء عند الحاجة."],
        "legalReviewNeeded": True,
        "overallScore": 76,
        "verdict": "مقبول كمسودة أولية ويحتاج مراجعة قانونية.",
    }


# ═══════════════════════════════════════════════════════════════
# Smart Legal Documents
# ═══════════════════════════════════════════════════════════════

async def generate_legal_schema(user_prompt: str) -> Dict[str, Any]:
    system = """
أنت Legal Document Schema Generator.
مهمتك تحليل طلب المستخدم وإنشاء JSON فقط بدون أي شرح.
يجب أن تستطيع إنشاء schema لأي مستند قانوني أو إداري، وليس فقط الأنواع المعروفة.

أرجع JSON بهذا الشكل فقط:
{
  "documentType": "camelCase",
  "documentTitle": "اسم المستند بالعربية",
  "documentCategory": "contract|agreement|letter|policy|minutes|notice|other",
  "confidence": 0.0,
  "jurisdiction": "الدولة أو غير محدد",
  "detectedParties": [],
  "summary": "وصف موجز",
  "extractedData": {},
  "missingRequiredFields": [],
  "fields": [
    {
      "name": "fieldName",
      "label": "التسمية بالعربية",
      "type": "text|number|date|textarea|select|checkbox|radio|email|phone|currency|file|repeater",
      "required": true,
      "value": null,
      "placeholder": "نص توضيحي",
      "options": [],
      "fields": []
    }
  ]
}

القواعد:
- لا تضع حقولاً غير ذات صلة.
- استخرج أي بيانات مذكورة في وصف المستخدم وضعها في value.
- الحقول الجوهرية اجعلها required: true.
- استخدم repeater عند وجود أطراف متعددة أو منتجات أو دفعات أو شهود أو بنود متكررة.
- إذا لم يذكر المستخدم الدولة وكان المستند يحتاج نظام قانوني، أضف حقل الدولة / القانون الحاكم.
- لا تكتب markdown.
- لا تكتب شرح.
- JSON فقط.
""".strip()
    try:
        content = await call_ragflow_chat([
            {"role": "system", "content": system},
            {"role": "user", "content": f"حلل هذا الطلب وأنشئ schema:\n{user_prompt}"},
        ])
        return normalize_schema(extract_json(content))
    except Exception:
        if AI_FALLBACK_ENABLED:
            return mock_legal_schema(user_prompt)
        raise


async def generate_legal_document(schema: Dict[str, Any], form_values: Dict[str, Any]) -> str:
    system = """
أنت متخصص في صياغة المستندات القانونية باللغة العربية.
اكتب مستندًا قانونيًا احترافيًا ومنظمًا.
اختر البنود المناسبة حسب نوع المستند فقط ولا تضع كل البنود القانونية دائماً.
لا تستخدم Markdown.
استخدم عناوين واضحة وأسطر فارغة.
في النهاية أضف: ملاحظة: هذا المستند صياغة أولية ويُنصح بمراجعة متخصص قانوني قبل الاعتماد الرسمي.
""".strip()
    prompt = f"""
نوع المستند:
{schema.get('documentTitle')}

تصنيف المستند:
{schema.get('documentCategory')}

ملخص:
{schema.get('summary')}

البيانات:
{json.dumps(form_values or {}, ensure_ascii=False, indent=2)}

اكتب المستند القانوني الكامل باللغة العربية.
""".strip()
    try:
        return await call_ragflow_chat([
            {"role": "system", "content": system},
            {"role": "user", "content": prompt},
        ], timeout=180)
    except Exception:
        if AI_FALLBACK_ENABLED:
            return mock_document(schema, form_values)
        raise


async def inspect_legal_document(doc_text: str, schema: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    system = """
أنت مراجع قانوني.
راجع المستند وأرجع JSON فقط بدون شرح خارجي.

الشكل المطلوب:
{
  "missingData": [],
  "weakClauses": [],
  "conflictingClauses": [],
  "risks": [],
  "improvements": [],
  "legalReviewNeeded": true,
  "overallScore": 0,
  "verdict": "تقييم مختصر"
}
""".strip()
    prompt = f"""
نوع المستند:
{(schema or {}).get('documentTitle', 'غير محدد')}

نص المستند:
{doc_text[:8000]}

راجع المستند.
""".strip()
    try:
        content = await call_ragflow_chat([
            {"role": "system", "content": system},
            {"role": "user", "content": prompt},
        ])
        data = extract_json(content)
        return {
            "missingData": data.get("missingData") or data.get("missingItems") or [],
            "weakClauses": data.get("weakClauses") or [],
            "conflictingClauses": data.get("conflictingClauses") or [],
            "risks": data.get("risks") or [],
            "improvements": data.get("improvements") or data.get("suggestions") or [],
            "legalReviewNeeded": bool(data.get("legalReviewNeeded", True)),
            "overallScore": int(data.get("overallScore") or data.get("score") or 70),
            "verdict": data.get("verdict") or "يحتاج مراجعة",
        }
    except Exception:
        if AI_FALLBACK_ENABLED:
            return mock_inspection()
        raise


# ═══════════════════════════════════════════════════════════════
# الخدمات القديمة: استشارة/تحليل/صياغة
# ═══════════════════════════════════════════════════════════════

async def search_ragflow(question: str, kb_ids=None) -> list:
    kb_ids = kb_ids or ([DEFAULT_KB_ID] if DEFAULT_KB_ID else [])
    if not RAGFLOW_API_KEY or not RAGFLOW_URL or not kb_ids:
        return []
    async with httpx.AsyncClient(timeout=30) as c:
        r = await c.post(
            f"{RAGFLOW_URL.rstrip('/')}/api/v1/retrieval",
            headers={"Authorization": f"Bearer {RAGFLOW_API_KEY}"},
            json={"question": question, "dataset_ids": kb_ids},
        )
        return r.json().get("data", {}).get("chunks", [])


async def ask_ollama(prompt: str, model: str = None) -> str:
    model = model or DEFAULT_MODEL
    async with httpx.AsyncClient(timeout=600) as c:
        r = await c.post(
            f"{OLLAMA_URL.rstrip('/')}/api/generate",
            json={"model": model, "prompt": prompt, "stream": False},
        )
        return r.json().get("response", "")


def build_context(chunks: list) -> str:
    out = ""
    for i, ch in enumerate(chunks[:5], 1):
        src = ch.get("document_keyword", "مصدر")
        out += f"\n[المصدر {i}: {src}]\n{ch.get('content','')[:800]}\n"
    return out


def format_sources(chunks: list, limit=5) -> list:
    return [{
        "document": c.get("document_keyword", ""),
        "content": c.get("content", "")[:300],
        "similarity": round(c.get("similarity", 0), 3),
    } for c in chunks[:limit]]


async def legal_consult(question: str, model: str = None) -> dict:
    # الأفضل استخدام Chat agent لأنه مرتبط بـ KB في RAGFlow، مع fallback للبحث القديم.
    try:
        answer = await call_ragflow_chat([
            {"role": "system", "content": "أنت مستشار قانوني. أجب بالعربية واذكر المراجع عند توفرها من قاعدة المعرفة."},
            {"role": "user", "content": question},
        ])
        return {"answer": answer, "sources": []}
    except Exception:
        chunks = await search_ragflow(question)
        if not chunks:
            return {"answer": "لم يتم العثور على معلومات ذات صلة في قاعدة المعرفة.", "sources": []}
        ctx = build_context(chunks)
        prompt = f"""أنت مستشار قانوني متخصص. أجب على السؤال بناءً على المصادر القانونية التالية فقط.
اذكر رقم المادة والمصدر في إجابتك. لا تخمّن أو تضِف معلومات غير موجودة في المصادر.

المصادر القانونية:
{ctx}

السؤال: {question}

الإجابة القانونية:"""
        answer = await ask_ollama(prompt, model)
        return {"answer": answer, "sources": format_sources(chunks)}


async def analyze_case(desc: str, documents: str = "") -> dict:
    chunks = await search_ragflow(desc)
    ctx = build_context(chunks)
    base = f"القضية: {desc}\nالمستندات: {documents}\nالمصادر: {ctx}"
    prompts = {
        "document_analysis": f"أنت محلل مستندات قانوني. حلل القضية واستخرج: الجدول الزمني، الأطراف، الأدلة، التناقضات.\n\n{base}\n\nالتحليل:",
        "legal_research": f"أنت باحث قانوني. حدد: القوانين والمواد المنطبقة بأرقامها، عبء الإثبات، الفجوات.\n\n{base}\n\nالبحث:",
        "adversarial": f"أنت محلل استراتيجي. ابنِ: حجج الخصم، نقاط ضعفنا، استراتيجيات الرد.\n\n{base}\n\nالتحليل:",
        "probability": f"أنت خبير تقدير نتائج. قيّم: فرص النجاح (نسبة مع تبرير)، السيناريوهات، التوصية.\n\n{base}\n\nالتقييم:",
    }
    results = await asyncio.gather(*[ask_ollama(p) for p in prompts.values()])
    agents = dict(zip(prompts.keys(), results))
    review = await ask_ollama(
        f"أنت مراجع قانوني ناقد. راجع وأشر إلى الهلوسة والتفاؤل المفرط والحجج الضعيفة.\n\n"
        f"{agents['document_analysis'][:500]}\n{agents['legal_research'][:500]}\n{agents['probability'][:300]}\n\nالمراجعة:")
    return {
        "document_analysis": agents["document_analysis"],
        "legal_research": agents["legal_research"],
        "adversarial_analysis": agents["adversarial"],
        "probability_assessment": agents["probability"],
        "critical_review": review,
        "sources": format_sources(chunks),
    }


async def draft_document(request: str, model: str = None) -> dict:
    # يستخدم RAGFlow chat لإنشاء مستند عام عند استعمال endpoint القديم.
    try:
        doc = await call_ragflow_chat([
            {"role": "system", "content": "أنت متخصص في صياغة المستندات القانونية باللغة العربية. اكتب مستنداً احترافياً منظماً."},
            {"role": "user", "content": request},
        ], timeout=180)
        return {"document": doc, "sources": []}
    except Exception:
        chunks = await search_ragflow(request)
        ctx = build_context(chunks)
        prompt = f"""أنت متخصص في صياغة المستندات القانونية. اصغ المستند بشكل احترافي مستخدماً المواد المناسبة.

المصادر:
{ctx}

المطلوب: {request}

المستند:"""
        doc = await ask_ollama(prompt, model)
        return {"document": doc, "sources": format_sources(chunks, 3)}
