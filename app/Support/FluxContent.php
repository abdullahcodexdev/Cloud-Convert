<?php

namespace App\Support;

class FluxContent
{
    public static function tools(): array
    {
        return json_decode(<<<'JSON'
[
  {
    "name": "Document",
    "formats": "PDF, DOCX, PPTX, XLSX",
    "icon": "bi-file-earmark-text"
  },
  {
    "name": "Media",
    "formats": "MP4, MOV, MP3, WAV",
    "icon": "bi-film"
  },
  {
    "name": "Images",
    "formats": "PNG, JPG, WEBP, SVG",
    "icon": "bi-image"
  },
  {
    "name": "Archive",
    "formats": "ZIP, RAR, 7Z, TAR",
    "icon": "bi-box-seam"
  }
]
JSON, true);
    }

    public static function toolGroups(): array
    {
        return json_decode(<<<'JSON'
[
  {
    "title": "Convert Files",
    "icon": "bi-arrow-repeat",
    "items": [
      "Archive Converter",
      "Audio Converter",
      "CAD Converter",
      "Document Converter",
      "Ebook Converter",
      "Font Converter",
      "Image Converter",
      "Presentation Converter",
      "Spreadsheet Converter",
      "Vector Converter",
      "Video Converter"
    ]
  },
  {
    "title": "Optimize Files",
    "icon": "bi-sliders",
    "items": [
      "Compress PDF",
      "Compress PNG",
      "Compress JPG",
      "PDF OCR"
    ]
  },
  {
    "title": "Merge Files",
    "icon": "bi-bezier2",
    "items": [
      "Merge PDF",
      "Merge Audio",
      "Merge Video",
      "Merge Image",
      "Merge Document",
      "Merge Spreadsheet",
      "Merge Presentation"
    ]
  },
  {
    "title": "Capture Websites",
    "icon": "bi-window",
    "items": [
      "Save Website as PDF",
      "Website PNG Screenshot",
      "Website JPG Screenshot"
    ]
  },
  {
    "title": "Archives",
    "icon": "bi-file-earmark-zip",
    "items": [
      "Create Archive",
      "Extract Archive"
    ]
  }
]
JSON, true);
    }

    public static function formatGroups(): array
    {
        return json_decode(<<<'JSON'
[
  {
    "name": "Archive",
    "formats": [
      "7Z",
      "ACE",
      "ALZ",
      "ARC",
      "ARJ",
      "BZ",
      "BZ2",
      "CAB",
      "CPIO",
      "DEB",
      "DMG",
      "GZ",
      "IMG",
      "ISO",
      "JAR",
      "LHA",
      "LZ",
      "LZMA",
      "LZO",
      "RAR",
      "RPM",
      "RZ",
      "TAR",
      "TAR.7Z",
      "TAR.BZ",
      "TAR.BZ2",
      "TAR.GZ",
      "TAR.LZO",
      "TAR.XZ",
      "TAR.Z",
      "TBZ",
      "TBZ2",
      "TGZ",
      "TZ",
      "TZO",
      "XZ",
      "Z",
      "ZIP"
    ]
  },
  {
    "name": "Audio",
    "formats": [
      "AAC",
      "AC3",
      "AIF",
      "AIFC",
      "AIFF",
      "AMR",
      "AU",
      "CAF",
      "DSS",
      "FLAC",
      "M4A",
      "M4B",
      "MP3",
      "OGA",
      "OGG",
      "VOC",
      "WAV",
      "WEBA",
      "WMA"
    ]
  },
  {
    "name": "CAD",
    "formats": [
      "DWG",
      "DXF",
      "IGES",
      "OBJ",
      "SKP",
      "STL"
    ]
  },
  {
    "name": "Document",
    "formats": [
      "ABW",
      "DJVU",
      "DOC",
      "DOCM",
      "DOCX",
      "DOT",
      "DOTX",
      "HTML",
      "HWP",
      "LWP",
      "MD",
      "ODT",
      "PAGES",
      "PDF",
      "RST",
      "RTF",
      "TEX",
      "TXT",
      "WPD",
      "WPS",
      "ZABW"
    ]
  },
  {
    "name": "Ebook",
    "formats": [
      "AZW",
      "AZW3",
      "AZW4",
      "CBC",
      "CBR",
      "CBZ",
      "CHM",
      "EPUB",
      "FB2",
      "HTM",
      "HTMLZ",
      "LIT",
      "LRF",
      "MOBI",
      "PDB",
      "PML",
      "PRC",
      "RB",
      "SNB",
      "TCR",
      "TXTZ"
    ]
  },
  {
    "name": "Font",
    "formats": [
      "EOT",
      "OTF",
      "TTF",
      "WOFF",
      "WOFF2"
    ]
  },
  {
    "name": "Image",
    "formats": [
      "3FR",
      "ARW",
      "AVIF",
      "BMP",
      "CR2",
      "CR3",
      "CRW",
      "DCR",
      "DNG",
      "EPS",
      "ERF",
      "GIF",
      "HEIC",
      "HEIF",
      "ICNS",
      "ICO",
      "JFIF",
      "JPEG",
      "JPG",
      "MOS",
      "MRW",
      "NEF",
      "ODD",
      "ODG",
      "ORF",
      "PEF",
      "PNG",
      "PPM",
      "PS",
      "PSB",
      "PSD",
      "PUB",
      "RAF",
      "RAW",
      "RW2",
      "TGA",
      "TIF",
      "TIFF",
      "WEBP",
      "X3F",
      "XCF",
      "XPS"
    ]
  },
  {
    "name": "Other",
    "formats": [
      "CSV",
      "ICS",
      "JSON",
      "XML"
    ]
  },
  {
    "name": "Presentation",
    "formats": [
      "DPS",
      "KEY",
      "ODP",
      "POT",
      "POTX",
      "PPS",
      "PPSX",
      "PPT",
      "PPTM",
      "PPTX"
    ]
  },
  {
    "name": "Spreadsheet",
    "formats": [
      "CSV",
      "ET",
      "NUMBERS",
      "ODS",
      "XLS",
      "XLSM",
      "XLSX"
    ]
  },
  {
    "name": "Vector",
    "formats": [
      "AI",
      "CDR",
      "CGM",
      "EMF",
      "SK",
      "SK1",
      "SVG",
      "SVGZ",
      "VSD",
      "WMF"
    ]
  },
  {
    "name": "Video",
    "formats": [
      "3GP",
      "AVI",
      "FLV",
      "M4V",
      "MKV",
      "MOV",
      "MP4",
      "WEBM",
      "WMV"
    ]
  }
]
JSON, true);
    }

    public static function formatDescriptions(): array
    {
        return json_decode(<<<'JSON'
{
  "PDF": "PDF is a document format designed to preserve layout, typography, and graphics across devices. It is widely used for contracts, reports, forms, and printable files.",
  "DOCX": "DOCX is a Microsoft Word document format used for editable text documents. It is commonly used for letters, reports, resumes, and business files.",
  "PPTX": "PPTX is a Microsoft PowerPoint presentation format used for slides, charts, and visual presentations. It is commonly used in meetings, classrooms, and sales decks.",
  "XLSX": "XLSX is a Microsoft Excel spreadsheet format used for tables, formulas, and structured business data. It is widely used for analysis, budgets, and reporting.",
  "JPG": "JPG, also known as JPEG, is a popular image format that uses lossy compression to reduce file size while keeping good visual quality. It is widely used for photos and web images.",
  "JPEG": "JPEG is a popular image format that uses lossy compression to reduce file size while keeping good visual quality. It is widely used for photos and web images.",
  "PNG": "PNG is an image format that supports lossless compression and transparent backgrounds. It is commonly used for interface graphics, logos, and high-quality web images.",
  "WEBP": "WEBP is a modern image format that provides strong compression while maintaining quality. It is often used on websites to improve performance and reduce image size.",
  "SVG": "SVG is a vector graphics format that scales without losing quality. It is commonly used for icons, logos, diagrams, and responsive web graphics.",
  "MP3": "MP3 is a compressed audio format widely used for music, podcasts, and voice content. It balances file size and audio quality for easy storage and sharing.",
  "WAV": "WAV is an uncompressed audio format known for high sound quality. It is often used in editing, recording, and professional audio workflows.",
  "MP4": "MP4 is a widely supported video container format used for online video, presentations, and media sharing. It offers good compression and compatibility across devices.",
  "MOV": "MOV is a multimedia container format commonly associated with Apple devices and professional editing workflows. It is often used for high-quality video files.",
  "ZIP": "ZIP is a compressed archive format used to bundle and reduce the size of files and folders. It is one of the most common formats for storage, download, and sharing.",
  "RAR": "RAR is a compressed archive format known for strong compression and multi-part archive support. It is commonly used for distributing large file collections.",
  "7Z": "7Z is a high-compression archive format used for efficiently packaging files and folders. It is often chosen when minimizing archive size is important."
}
JSON, true);
    }

    public static function steps(): array
    {
        return json_decode(<<<'JSON'
[
  {
    "title": "Upload Securely",
    "text": "Import files from your device or cloud storage with a clear, focused workflow."
  },
  {
    "title": "Choose Output",
    "text": "Pick a conversion target and set professional-grade options only when you need them."
  },
  {
    "title": "Export Fast",
    "text": "Process jobs quickly and download results through a simple dashboard experience."
  }
]
JSON, true);
    }

    public static function pricing(): array
    {
        return json_decode(<<<'JSON'
{
  "monthly": [
    {
      "name": "Starter",
      "price": 9,
      "description": "For freelancers and light personal conversion needs.",
      "recommended": false,
      "features": [
        "500 conversion minutes",
        "25 GB storage",
        "Email support"
      ]
    },
    {
      "name": "Pro",
      "price": 19,
      "description": "Best for creators and growing teams with recurring workloads.",
      "recommended": true,
      "features": [
        "2,000 conversion minutes",
        "200 GB storage",
        "Priority queue",
        "API access"
      ]
    },
    {
      "name": "Business",
      "price": 49,
      "description": "For production use, automation, and heavier throughput.",
      "recommended": false,
      "features": [
        "7,500 conversion minutes",
        "1 TB storage",
        "Team seats",
        "Advanced API"
      ]
    }
  ],
  "yearly": [
    {
      "name": "Starter",
      "price": 90,
      "description": "For freelancers and light personal conversion needs.",
      "recommended": false,
      "features": [
        "500 conversion minutes",
        "25 GB storage",
        "Email support"
      ]
    },
    {
      "name": "Pro",
      "price": 190,
      "description": "Best for creators and growing teams with recurring workloads.",
      "recommended": true,
      "features": [
        "2,000 conversion minutes",
        "200 GB storage",
        "Priority queue",
        "API access"
      ]
    },
    {
      "name": "Business",
      "price": 490,
      "description": "For production use, automation, and heavier throughput.",
      "recommended": false,
      "features": [
        "7,500 conversion minutes",
        "1 TB storage",
        "Team seats",
        "Advanced API"
      ]
    }
  ]
}
JSON, true);
    }

}
