# Cloud-Convert — Online File Converter

A full-featured file-conversion platform built with **Laravel**. Convert between **80+ formats** across documents, audio, video, images, archives, and fonts — and merge multiple files into one. A Vue-powered single-page interface with drag-and-drop uploads, live format picker, conversion history, and authentication.

> Inspired by CloudConvert, rebuilt as a self-hosted Laravel application.

## ✨ Features

- **80+ conversions** — the engine advertises only what the server can actually do (capability-aware).
  - **Documents:** PDF, DOCX, DOC, TXT, HTML, ODT, RTF, CSV, XML, XLS, XLSX, EPUB, PPT, PPTX, and more
  - **Images:** JPG, PNG, GIF, WEBP, AVIF, BMP, TIFF (GD-powered)
  - **Audio & Video:** MP3, WAV, OGG, AAC, FLAC, WMA / MP4, MOV, AVI, MKV, WEBM, WMV, FLV, 3GP, M4V (FFmpeg)
  - **Archives:** ZIP, RAR, 7Z, TAR, GZ … (7-Zip)
  - **Fonts:** TTF, OTF, WOFF, WOFF2 (Python + fontTools)
- **Merge files** — join 2 or more files into one:
  - PDF, Audio, Video, Image → PDF, Document → PDF, Spreadsheet → XLSX, Presentation → PPTX
- **Merge PDF** with page-perfect output (FPDI)
- **Conversion history**, multi-file download, and download-all as ZIP
- **Authentication** — local accounts, Google & Microsoft OAuth, two-step verification, password reset
- **Automatic storage cleanup** — scheduled command removes old files so disk never fills up
- **Upload size limits & API rate limiting** out of the box

## 🛠️ Tech Stack

Laravel · PHP 8.2+ · Vue 3 · Bootstrap · FFmpeg · 7-Zip · Dompdf · PhpSpreadsheet · PhpWord · FPDI

## ✅ Requirements

- PHP 8.2+ with `gd`, `zip`, `fileinfo`, `dom`, `xml`
- Composer
- **FFmpeg** on PATH — audio/video conversions & merging
- **7-Zip** — archive conversions (optional)
- **Poppler `pdftotext`** or `smalot/pdfparser` — PDF text extraction
- **Python + fontTools** — font conversions (optional)
- **Calibre `ebook-convert`** — MOBI export (optional)

## 🚀 Install

```bash
git clone https://github.com/abdullahcodexdev/Cloud-Convert.git
cd Cloud-Convert
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan serve
```

Open: **http://127.0.0.1:8000**

To enable automatic cleanup of old converted files, run the scheduler (or trigger manually):

```bash
php artisan files:cleanup           # delete files older than 48h
php artisan schedule:run            # run scheduled tasks (set up via cron / Task Scheduler)
```

## 🔌 Key Routes

| Method | Route | Purpose |
|--------|-------|---------|
| `GET`  | `/` | Home / converter UI |
| `GET`  | `/api/highlights` | Supported formats & conversion map |
| `POST` | `/api/convert` | Convert uploaded files |
| `POST` | `/api/merge` | Merge files (pdf, audio, video, image, document, spreadsheet, presentation) |
| `POST` | `/api/merge-pdf` | Merge PDF files |
| `GET`  | `/api/download/{fileId}` | Download a converted file |
| `POST` | `/api/download-all` | Download all as ZIP |

## ⚙️ Configuration

Copy `.env.example` to `.env` and set your own keys for OAuth (`GOOGLE_*`, `MICROSOFT_*`), mail, and the optional AI support assistant. **Never commit your `.env`.**

## 📄 License

For portfolio / educational use.
