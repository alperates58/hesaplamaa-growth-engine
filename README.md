# Hesaplamaa Growth Engine — Kurulum Notu

## Klasör Yapısı

```
hesaplamaa-growth-engine/
├── hesaplamaa-growth-engine.php    ← Ana plugin dosyası
├── uninstall.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── includes/
│   ├── Core/
│   │   └── class-plugin.php        ← Singleton, tüm hook'lar
│   ├── admin/
│   │   ├── class-menu.php
│   │   ├── class-dashboard.php
│   │   ├── class-keyword-opportunities.php
│   │   ├── class-page-analysis.php
│   │   ├── class-new-ideas.php
│   │   ├── class-settings.php
│   │   └── class-system-status.php
│   ├── api/
│   │   ├── class-gsc-client.php    ← OAuth2 + Search Analytics
│   │   └── class-suggest-client.php
│   ├── db/
│   │   ├── class-migrator.php      ← dbDelta tabloları
│   │   └── class-repository.php   ← Tüm DB işlemleri
│   └── cron/
│       └── class-scheduler.php    ← Günlük sync
├── templates/admin/
│   ├── dashboard.php
│   ├── settings.php
│   ├── opportunities.php
│   ├── new-ideas.php
│   ├── page-analysis.php
│   └── system-status.php
└── languages/

```

## Kurulum Adımları

1. Klasörü `/wp-content/plugins/` altına koy
2. WordPress admin → Plugins → Activate
3. **Ayarlar > GSC Ayarları** → Google Cloud Console'dan OAuth credentials oluştur
4. Redirect URI olarak şunu ekle: `https://hesaplamaa.com/wp-admin/?hge_gsc_callback=1`
5. Client ID + Secret'ı kaydet → Google ile Bağlan
6. İlk sync için Dashboard → "Şimdi Senkronize Et"

## Sonraki Aşama (V2)

- [ ] Google Ads Keyword Planner entegrasyonu (aylık hacim verisi)
- [ ] Pozisyon değişim tracking (günlük delta)
- [ ] E-posta raporu (haftalık özet)
- [ ] Export (CSV/Excel)
- [ ] Google Trends mini widget
