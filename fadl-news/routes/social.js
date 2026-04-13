const express = require('express');
const router = express.Router();
const fetch = require('node-fetch');
const { getDb } = require('../database/init');
const { requireAuth } = require('../middleware/auth');

// Post to Facebook
async function postToFacebook(article, baseUrl, settings) {
  const pageId = process.env.FACEBOOK_PAGE_ID || settings.facebook_page_id;
  const token = process.env.FACEBOOK_ACCESS_TOKEN || settings.facebook_access_token;
  if (!pageId || !token) throw new Error('Facebook credentials not configured');

  const message = `${article.title}\n\n${article.excerpt || ''}\n\nاقرأ المزيد: ${baseUrl}/article/${article.slug}`;
  const body = { message, access_token: token };
  if (article.image_url) body.link = `${baseUrl}/article/${article.slug}`;

  const resp = await fetch(`https://graph.facebook.com/v19.0/${pageId}/feed`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  const data = await resp.json();
  if (!resp.ok) throw new Error(data.error?.message || 'Facebook API error');
  return data.id;
}

// Post to LinkedIn
async function postToLinkedIn(article, baseUrl, settings) {
  const orgId = process.env.LINKEDIN_ORGANIZATION_ID || settings.linkedin_org_id;
  const token = process.env.LINKEDIN_ACCESS_TOKEN || settings.linkedin_access_token;
  if (!orgId || !token) throw new Error('LinkedIn credentials not configured');

  const body = {
    author: `urn:li:organization:${orgId}`,
    lifecycleState: 'PUBLISHED',
    specificContent: {
      'com.linkedin.ugc.ShareContent': {
        shareCommentary: { text: `${article.title}\n\n${article.excerpt || ''}\n\n${baseUrl}/article/${article.slug}` },
        shareMediaCategory: 'ARTICLE',
        media: [{
          status: 'READY',
          description: { text: article.excerpt || article.title },
          originalUrl: `${baseUrl}/article/${article.slug}`,
          title: { text: article.title }
        }]
      }
    },
    visibility: { 'com.linkedin.ugc.MemberNetworkVisibility': 'PUBLIC' }
  };

  const resp = await fetch('https://api.linkedin.com/v2/ugcPosts', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  const data = await resp.json();
  if (!resp.ok) throw new Error(data.message || 'LinkedIn API error');
  return data.id;
}

// Post to X (Twitter)
async function postToTwitter(article, baseUrl, settings) {
  const apiKey = process.env.TWITTER_API_KEY || settings.twitter_api_key;
  const apiSecret = process.env.TWITTER_API_SECRET || settings.twitter_api_secret;
  const accessToken = process.env.TWITTER_ACCESS_TOKEN || settings.twitter_access_token;
  const accessSecret = process.env.TWITTER_ACCESS_SECRET || settings.twitter_access_secret;
  if (!apiKey || !apiSecret || !accessToken || !accessSecret)
    throw new Error('Twitter credentials not configured');

  // OAuth 1.0a signing
  const crypto = require('crypto');
  const url = 'https://api.twitter.com/2/tweets';
  const text = `${article.title}\n${baseUrl}/article/${article.slug}`;
  const nonce = crypto.randomBytes(16).toString('hex');
  const timestamp = Math.floor(Date.now() / 1000).toString();

  const oauthParams = {
    oauth_consumer_key: apiKey,
    oauth_nonce: nonce,
    oauth_signature_method: 'HMAC-SHA1',
    oauth_timestamp: timestamp,
    oauth_token: accessToken,
    oauth_version: '1.0'
  };

  const paramStr = Object.keys(oauthParams).sort()
    .map(k => `${encodeURIComponent(k)}=${encodeURIComponent(oauthParams[k])}`).join('&');
  const sigBase = `POST&${encodeURIComponent(url)}&${encodeURIComponent(paramStr)}`;
  const sigKey = `${encodeURIComponent(apiSecret)}&${encodeURIComponent(accessSecret)}`;
  const signature = crypto.createHmac('sha1', sigKey).update(sigBase).digest('base64');

  const authHeader = 'OAuth ' + Object.keys(oauthParams).sort().map(k =>
    `${encodeURIComponent(k)}="${encodeURIComponent(oauthParams[k])}"`
  ).join(', ') + `, oauth_signature="${encodeURIComponent(signature)}"`;

  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Authorization': authHeader, 'Content-Type': 'application/json' },
    body: JSON.stringify({ text: text.substring(0, 280) })
  });
  const data = await resp.json();
  if (!resp.ok) throw new Error(data.detail || 'Twitter API error');
  return data.data?.id;
}

// Main function to post to all platforms
async function postToAllSocial(article, baseUrl, settings) {
  const db = getDb();
  const platforms = [
    { name: 'facebook', enabled: settings.auto_post_facebook === '1', fn: postToFacebook },
    { name: 'linkedin', enabled: settings.auto_post_linkedin === '1', fn: postToLinkedIn },
    { name: 'twitter', enabled: settings.auto_post_twitter === '1', fn: postToTwitter },
  ];

  for (const p of platforms) {
    if (!p.enabled) continue;
    try {
      const postId = await p.fn(article, baseUrl, settings);
      await db.execute(
        'INSERT INTO social_posts (article_id, platform, post_id, status) VALUES (?, ?, ?, ?)',
        [article.id, p.name, postId || null, 'success']
      );
    } catch (e) {
      await db.execute(
        'INSERT INTO social_posts (article_id, platform, status, error_message) VALUES (?, ?, ?, ?)',
        [article.id, p.name, 'failed', e.message]
      );
    }
  }
}

// Manual post endpoint
router.post('/post/:articleId', requireAuth, async (req, res, next) => {
  try {
    const db = getDb();
    const article = await db.queryOne(`
      SELECT a.*, u.username as author_name, c.name as category_name
      FROM articles a LEFT JOIN users u ON a.author_id = u.id LEFT JOIN categories c ON a.category_id = c.id
      WHERE a.id = ?
    `, [req.params.articleId]);
    if (!article) return res.status(404).json({ error: 'المقال غير موجود' });

    const settings = {};
    const rows = await db.query('SELECT `key`, value FROM settings');
    rows.forEach(r => { settings[r.key] = r.value; });
    const baseUrl = process.env.BASE_URL || `http://${req.get('host')}`;
    const { platforms } = req.body;
    const results = {};

    const fns = { facebook: postToFacebook, linkedin: postToLinkedIn, twitter: postToTwitter };
    for (const p of (platforms || ['facebook', 'linkedin', 'twitter'])) {
      if (!fns[p]) continue;
      try {
        const postId = await fns[p](article, baseUrl, settings);
        await db.execute(
          'INSERT INTO social_posts (article_id, platform, post_id, status) VALUES (?, ?, ?, ?)',
          [article.id, p, postId || null, 'success']
        );
        results[p] = { success: true };
      } catch (e) {
        await db.execute(
          'INSERT INTO social_posts (article_id, platform, status, error_message) VALUES (?, ?, ?, ?)',
          [article.id, p, 'failed', e.message]
        );
        results[p] = { success: false, error: e.message };
      }
    }
    res.json({ results });
  } catch (err) { next(err); }
});

// GET social post history
router.get('/history/:articleId', requireAuth, async (req, res, next) => {
  try {
    const db = getDb();
    const posts = await db.query(
      'SELECT * FROM social_posts WHERE article_id = ? ORDER BY created_at DESC',
      [req.params.articleId]
    );
    res.json({ posts });
  } catch (err) { next(err); }
});

module.exports = router;
module.exports.postToAllSocial = postToAllSocial;
