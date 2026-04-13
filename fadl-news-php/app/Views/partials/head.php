<?php
/**
 * Partial: <head>
 * Expected vars: $title, $description, $canonical, $settings, $ogImage, $ogType, $extraHead
 */
$title       = $title       ?? ($settings['site_name'] ?? '');
$description = $description ?? ($settings['site_description'] ?? '');
$canonical   = $canonical   ?? base_url();
$ogImage     = $ogImage     ?? '';
$ogType      = $ogType      ?? 'website';
$extraHead   = $extraHead   ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> | <?= e($settings['site_name'] ?? '') ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <meta name="keywords" content="<?= e($settings['site_keywords'] ?? '') ?>">
  <meta name="author" content="<?= e($settings['site_name'] ?? '') ?>">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= e($canonical) ?>">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:site_name" content="<?= e($settings['site_name'] ?? '') ?>">
  <meta property="og:locale" content="ar_AR">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($title) ?>">
  <meta name="twitter:description" content="<?= e($description) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">

  <!-- Google Fonts: Cairo -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons@4.29.2/dist/feather.min.js" defer></script>

  <!-- Main CSS -->
  <link rel="stylesheet" href="/css/style.css">

  <!-- Sitemap links -->
  <link rel="sitemap" type="application/xml" href="/sitemap.xml">

  <?= $extraHead ?>
</head>
<body>
