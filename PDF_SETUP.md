PDF setup (Dompdf)

1. Install via Composer in the project root:

```bash
composer require dompdf/dompdf
```

2. Ensure PHP has the `ext-gd` or `ext-zip` extensions enabled for best output.

3. After install, `invoice_pdf.php` will use Dompdf automatically and allow customers to download invoices as PDFs.

Notes:
- If you cannot run Composer on the server, upload the `vendor/` folder from a local machine where you ran Composer.
- For production-quality PDFs consider using a dedicated rendering service or wkhtmltopdf if you need more advanced CSS support.
