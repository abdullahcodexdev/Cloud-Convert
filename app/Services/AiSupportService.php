<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSupportService
{
    private const FORMATS = [
        '3gp', '7z', 'aac', 'avi', 'bmp', 'csv', 'doc', 'docx', 'epub', 'gif', 'html', 'jpeg', 'jpg',
        'mkv', 'mobi', 'mov', 'mp3', 'mp4', 'odt', 'pdf', 'png', 'ppt', 'pptx', 'rar', 'rtf', 'svg',
        'txt', 'wav', 'webm', 'webp', 'xls', 'xlsx', 'xml', 'zip',
    ];

    public function reply(string $message, array $history = []): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'Please type your question and I will help you with FluxConvert.';
        }

        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            return $this->fallbackReply($message, $history);
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-5.2'),
                    'instructions' => $this->systemInstructions(),
                    'input' => $this->responseInput($message, $history),
                    'max_output_tokens' => (int) config('services.openai.max_output_tokens', 450),
                ]);

            if (! $response->successful()) {
                Log::warning('AI support request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->fallbackReply($message, $history);
            }

            $reply = $this->extractResponseText($response->json());

            return $reply !== '' ? $reply : $this->fallbackReply($message, $history);
        } catch (\Throwable $exception) {
            Log::warning('AI support request error.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->fallbackReply($message, $history);
        }
    }

    private function systemInstructions(): string
    {
        return implode("\n", [
            'You are FluxConvert AI Support, a professional support assistant on the FluxConvert website.',
            'FluxConvert converts files including documents, images, audio, video, spreadsheets, presentations, ebooks, and archives.',
            'Help users with format choices, upload steps, conversion steps, download issues, account/profile features, and WhatsApp support.',
            'Answer every user message naturally and helpfully. Never repeat a generic welcome after the conversation has started.',
            'Use the previous chat context to understand short replies like "ok", "jpg", "yes", or "it failed".',
            'If the user gives one format, ask for the missing source or target format. If the user gives two formats, give exact conversion steps.',
            'If the user reports a technical problem, ask for source format, target format, file size, browser, and exact error.',
            'Do not promise that a human has been contacted unless the user opens WhatsApp.',
            'Keep replies concise, professional, and practical. Prefer 2 to 5 short sentences.',
        ]);
    }

    private function responseInput(string $message, array $history): array
    {
        $messages = [];

        foreach (array_slice($history, -6) as $item) {
            $role = ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function extractResponseText(array $payload): string
    {
        $outputText = trim((string) data_get($payload, 'output_text', ''));
        if ($outputText !== '') {
            return $outputText;
        }

        $parts = [];
        foreach ((array) data_get($payload, 'output', []) as $output) {
            foreach ((array) data_get($output, 'content', []) as $content) {
                $text = trim((string) data_get($content, 'text', ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function fallbackReply(string $message, array $history = []): string
    {
        $text = strtolower($message);
        $lastAssistantReply = '';
        $assistantReplyCount = 0;
        foreach (array_reverse($history) as $item) {
            if (($item['role'] ?? '') === 'assistant') {
                $assistantReplyCount++;
                if ($lastAssistantReply === '') {
                    $lastAssistantReply = (string) ($item['content'] ?? '');
                }
            }
        }

        if (preg_match('/^(ok|okay|yes|done|fine|alright|thanks|thank you|thx)$/i', $message)) {
            return $this->acknowledgementReply($history, $lastAssistantReply, $assistantReplyCount);
        }

        $conversion = $this->conversionFromConversation($message, $history);
        if ($conversion !== null) {
            return $this->conversionReply($conversion['source'], $conversion['target'], $lastAssistantReply, $assistantReplyCount);
        }

        if (preg_match('/\b(hi|hello|hey|how are you|salam|assalam)\b/i', $message)) {
            if (preg_match('/\bhow are you\b/i', $message)) {
                $reply = 'I am doing well and ready to help with FluxConvert. Tell me what file type you are working with, for example MP4 to 3GP, PDF to Word, or JPG to PNG.';
                if ($lastAssistantReply === $reply) {
                    return 'Still here. Send me the source format and target format, and I will guide you step by step.';
                }

                return $reply;
            }

            $reply = 'Hi, welcome to FluxConvert Support. What do you need help with today: file conversion, download problem, account access, or WhatsApp support?';
            if ($lastAssistantReply === $reply) {
                return 'You can ask something like "convert PDF to Word", "MP4 download issue", or "which formats are supported?"';
            }

            return $reply;
        }

        if (preg_match('/\b(convert|conversion|change|make)\b/i', $message)) {
            $formats = $this->formatsInText($message);
            if (count($formats) === 1) {
                return $this->singleFormatReply($formats[0], $lastAssistantReply, $assistantReplyCount);
            }

            return $this->nonRepeatingReply([
                'Sure, I can help with the conversion. Please tell me both formats, for example PDF to JPG, MP4 to 3GP, DOCX to PDF, or PNG to WebP.',
                'To guide you correctly, I need the source format and target format. Example: "convert PDF to JPG" or "convert MP4 to 3GP".',
                'Please share the exact conversion pair you need. After that, I will give you the best steps for FluxConvert.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        $formats = $this->formatsInText($message);
        if (count($formats) === 1) {
            return $this->singleFormatReply($formats[0], $lastAssistantReply, $assistantReplyCount);
        }

        if (str_contains($text, 'download') || str_contains($text, 'corrupt')) {
            return $this->nonRepeatingReply([
                'For download issues, convert the file again, then use the new Download button. If it still fails, tell me the source format, target format, file size, and the exact browser error.',
                'If the downloaded file is corrupt, share the source format, target format, and file size. I can then guide you on whether to retry, choose another target, or report a conversion issue.',
                'A corrupt download usually means the conversion result was incomplete or the browser received the wrong file. Try converting once more and confirm the target format you selected.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (str_contains($text, 'mp4') || str_contains($text, 'video')) {
            return $this->nonRepeatingReply([
                'FluxConvert supports video conversions such as MP4, MOV, AVI, MKV, WEBM, 3GP, and GIF. Upload the video, choose the target format, convert, then download the new result.',
                'For MP4 or video issues, tell me the target format and whether the problem happens during conversion or only after download.',
                'If you are converting MP4 to 3GP, use a short test video first. Older 3GP players can reject modern audio/video codecs even when conversion finishes.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (str_contains($text, 'pdf') || str_contains($text, 'word') || str_contains($text, 'doc')) {
            return $this->documentReply($text, $lastAssistantReply, $assistantReplyCount);
        }

        if (str_contains($text, 'account') || str_contains($text, 'sign')) {
            return $this->nonRepeatingReply([
                'You can sign in to save conversion history and access My Files, Profile, and Settings. Use the Sign In button in the top navigation.',
                'For account help, use Sign In first. After login, you can manage Profile, Settings, and your saved conversion history.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (preg_match('/\b(help|need help|support|problem|issue)\b/i', $message)) {
            return $this->nonRepeatingReply([
                'Sure. What do you need help with? Choose one: file conversion, download problem, MP4/video issue, PDF/document issue, image conversion, or account/sign-in.',
                'Tell me the source file type and the target file type, for example PDF to PNG, MP4 to 3GP, or JPG to PDF.',
                'I can help. Please write the conversion you want or the exact error you see after clicking Convert or Download.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        return $this->nonRepeatingReply([
            'I can help. Please tell me what you want to do, for example "convert PDF to JPG", "MP4 download issue", or "which formats are supported?"',
            'Please send the source format and target format, or describe the exact problem you are seeing on FluxConvert.',
            'Tell me the file type you uploaded, the format you selected, and what happened after clicking Convert or Download.',
        ], $lastAssistantReply, $assistantReplyCount);
    }

    private function conversionFromConversation(string $message, array $history): ?array
    {
        $direct = $this->conversionPairFromText($message);
        if ($direct !== null) {
            return $direct;
        }

        $currentFormats = $this->formatsInText($message);
        if (count($currentFormats) !== 1) {
            return null;
        }

        $target = $currentFormats[0];
        foreach (array_reverse($history) as $item) {
            if (($item['role'] ?? '') !== 'user') {
                continue;
            }

            $previousFormats = $this->formatsInText((string) ($item['content'] ?? ''));
            if (count($previousFormats) === 1 && $previousFormats[0] !== $target) {
                return [
                    'source' => $previousFormats[0],
                    'target' => $target,
                ];
            }

            $previousPair = $this->conversionPairFromText((string) ($item['content'] ?? ''));
            if ($previousPair !== null && $previousPair['target'] !== $target) {
                return [
                    'source' => $previousPair['target'],
                    'target' => $target,
                ];
            }
        }

        return null;
    }

    private function conversionPairFromText(string $text): ?array
    {
        $formatPattern = implode('|', array_map('preg_quote', self::FORMATS));
        if (preg_match('/\b(' . $formatPattern . ')\b\s*(?:to|2|into|as|->)\s*\b(' . $formatPattern . ')\b/i', $text, $matches)) {
            return [
                'source' => strtolower($matches[1]),
                'target' => strtolower($matches[2]),
            ];
        }

        if (preg_match('/\bconvert(?:\s+\w+){0,4}?\s+\b(' . $formatPattern . ')\b(?:\s+\w+){0,4}?\s+(?:to|2|into|as|->)\s*\b(' . $formatPattern . ')\b/i', $text, $matches)) {
            return [
                'source' => strtolower($matches[1]),
                'target' => strtolower($matches[2]),
            ];
        }

        return null;
    }

    private function formatsInText(string $text): array
    {
        $formats = [];
        foreach (self::FORMATS as $format) {
            if (preg_match('/\b' . preg_quote($format, '/') . '\b/i', $text)) {
                $formats[] = $format;
            }
        }

        usort($formats, fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return array_values(array_unique($formats));
    }

    private function conversionReply(string $source, string $target, string $lastAssistantReply, int $assistantReplyCount): string
    {
        $sourceLabel = strtoupper($source);
        $targetLabel = strtoupper($target);

        if ($source === $target) {
            return "Your source and target are both {$sourceLabel}. Please choose a different output format, such as PDF to JPG, JPG to PNG, or MP4 to 3GP.";
        }

        if ($source === 'pdf' && in_array($target, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $this->nonRepeatingReply([
                "Yes, FluxConvert can guide {$sourceLabel} to {$targetLabel}. Upload your PDF, select {$targetLabel}, click Convert, then download the result. If the PDF has many pages, the output may be multiple images or a ZIP.",
                "{$sourceLabel} to {$targetLabel} is an image conversion. If it fails, the server needs PDF image rendering support such as Imagick and Ghostscript.",
                "For best {$sourceLabel} to {$targetLabel} results, use a clear PDF and wait until conversion fully completes before clicking Download.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (in_array($source, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true) || in_array($target, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true)) {
            return $this->nonRepeatingReply([
                "Yes, you can convert {$sourceLabel} to {$targetLabel}. Upload the {$sourceLabel} file, choose {$targetLabel}, click Convert, and download the converted file when the Download button appears.",
                "{$sourceLabel} to {$targetLabel} is an image conversion. Use PNG for transparency, JPG for smaller photos, and WebP for modern web images.",
                "For {$sourceLabel} to {$targetLabel}, make sure the uploaded image is not damaged. If conversion fails, try a smaller file or another output format.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (in_array($source, ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp'], true) || in_array($target, ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp', 'gif'], true)) {
            return $this->nonRepeatingReply([
                "Yes, FluxConvert supports {$sourceLabel} to {$targetLabel}. Upload the video, select {$targetLabel}, wait for conversion to finish, then use the Download button.",
                "For {$sourceLabel} to {$targetLabel}, keep the browser tab open until conversion completes. Large videos can take longer than documents or images.",
                "If {$sourceLabel} to {$targetLabel} downloads but will not play, tell me the file size and target format so I can suggest the best compatible settings.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (in_array($source, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'html', 'odt'], true) || in_array($target, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'html', 'odt'], true)) {
            return $this->nonRepeatingReply([
                "Yes, you can convert {$sourceLabel} to {$targetLabel}. Upload the document, select {$targetLabel}, click Convert, then download the finished file.",
                "For {$sourceLabel} to {$targetLabel}, use DOCX when you need editable text and PDF when you need a fixed layout for sharing or printing.",
                "If the source is a scanned PDF, text formats like DOCX or TXT may need OCR. Image or PDF targets usually work better for scanned pages.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        return $this->nonRepeatingReply([
            "Yes, FluxConvert can help with {$sourceLabel} to {$targetLabel}. Upload your file, choose {$targetLabel}, start conversion, and download the result after it finishes.",
            "For {$sourceLabel} to {$targetLabel}, make sure the selected target is supported on the converter page before starting.",
            "If {$sourceLabel} to {$targetLabel} fails, send me the file size and the exact error message so I can help troubleshoot.",
        ], $lastAssistantReply, $assistantReplyCount);
    }

    private function singleFormatReply(string $format, string $lastAssistantReply, int $assistantReplyCount): string
    {
        $label = strtoupper($format);

        if (in_array($format, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true)) {
            return $this->nonRepeatingReply([
                "{$label} is an image format. Tell me the source format too, for example PDF to {$label}, PNG to {$label}, or {$label} to PDF.",
                "I can help with {$label}. Please write the full conversion pair, such as PDF to {$label} or {$label} to PNG.",
                "For {$label}, FluxConvert can handle common image conversions. Send both formats and I will guide you step by step.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if ($format === 'pdf') {
            return $this->nonRepeatingReply([
                'PDF can be converted to formats like DOCX, TXT, JPG, PNG, HTML, or back from images/documents. Which target format do you need?',
                'Tell me your PDF conversion target, for example PDF to JPG, PDF to Word, PDF to PNG, or JPG to PDF.',
                'I can help with PDF. If it is scanned, image output is usually easier; editable text may need OCR.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (in_array($format, ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp'], true)) {
            return $this->nonRepeatingReply([
                "{$label} is a video format. Tell me the target format, for example {$label} to 3GP, {$label} to MP4, or {$label} to GIF.",
                "I can help with {$label} conversion. Please send the full pair, such as MP4 to 3GP or MOV to MP4.",
                "For {$label}, large files can take longer. Share the target format and I will guide you.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        return $this->nonRepeatingReply([
            "I can help with {$label}. Please send the full conversion pair, for example {$label} to PDF or PDF to {$label}.",
            "Which format do you want to convert {$label} into? Send the target format and I will guide you.",
        ], $lastAssistantReply, $assistantReplyCount);
    }

    private function acknowledgementReply(array $history, string $lastAssistantReply, int $assistantReplyCount): string
    {
        $lastUserMessage = '';
        foreach (array_reverse($history) as $item) {
            if (($item['role'] ?? '') === 'user') {
                $lastUserMessage = (string) ($item['content'] ?? '');
                break;
            }
        }

        $conversion = $lastUserMessage !== '' ? $this->conversionFromConversation($lastUserMessage, $history) : null;
        if ($conversion !== null) {
            $source = strtoupper($conversion['source']);
            $target = strtoupper($conversion['target']);

            return $this->nonRepeatingReply([
                "Great. For {$source} to {$target}, upload your file, select {$target}, click Convert, and wait until the Download button appears. If anything fails, send me the exact error.",
                "Understood. Start the {$source} to {$target} conversion from the converter page. Keep the tab open until it finishes, then download the result.",
                "Perfect. If the {$source} to {$target} file does not download correctly, tell me the file size and browser error so I can help troubleshoot.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if ($lastAssistantReply !== '') {
            return $this->nonRepeatingReply([
                'Understood. Continue with those steps, and send the exact error if anything does not work.',
                'Okay. Send the source format, target format, and any error message if you need more help.',
                'Got it. I can also help with conversion steps, download issues, supported formats, and account access.',
            ], $lastAssistantReply, $assistantReplyCount);
        }

        return 'Understood. Tell me the file type you have and the format you want to convert it into.';
    }

    private function documentReply(string $text, string $lastAssistantReply, int $assistantReplyCount): string
    {
        if (preg_match('/\bpdf\s*(to|2|->)\s*(png|jpg|jpeg|image|images)\b/', $text, $matches)) {
            $target = strtoupper($matches[2] === 'image' || $matches[2] === 'images' ? 'PNG/JPG' : $matches[2]);

            return $this->nonRepeatingReply([
                "For PDF to {$target}, upload the PDF, choose {$target} as the target, then click Convert. If the PDF has multiple pages, the result may download as images or an archive depending on the converter output.",
                "PDF to {$target} works best when the PDF pages are clear. If conversion fails, the server may need Imagick and Ghostscript enabled for PDF image rendering.",
                "To convert PDF pages into {$target}, select PDF as source and {$target} as target. If you need every page, check whether the downloaded result contains all pages.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        if (preg_match('/\b(pdf|docx?|word)\s*(to|2|->)\s*(docx?|word|pdf|txt|html)\b/', $text, $matches)) {
            $source = strtoupper(str_replace('WORD', 'DOCX', $matches[1]));
            $target = strtoupper(str_replace('WORD', 'DOCX', $matches[3]));

            return $this->nonRepeatingReply([
                "For {$source} to {$target}, upload the document, choose {$target}, and start conversion. If the PDF is scanned, text export may need OCR.",
                "{$source} to {$target} is a document conversion. If formatting looks different after conversion, try DOCX for editable text or PDF for fixed layout.",
                "Use {$target} when you need that output format. For scanned PDFs, normal text conversion may not detect words unless OCR is available.",
            ], $lastAssistantReply, $assistantReplyCount);
        }

        return $this->nonRepeatingReply([
            'FluxConvert can help with PDF and document conversions. Select your document, choose a supported target like DOCX, PDF, TXT, HTML, JPG, or PNG, then start conversion.',
            'For document conversion, tell me the exact pair such as PDF to PNG, PDF to Word, DOCX to PDF, or TXT to PDF. I will give the correct steps.',
            'If your PDF is scanned or image-only, text formats like DOCX or TXT may require OCR. Image targets like PNG or JPG need PDF image rendering support.',
        ], $lastAssistantReply, $assistantReplyCount);
    }

    private function nonRepeatingReply(array $replies, string $lastAssistantReply, int $assistantReplyCount): string
    {
        $replies = array_values(array_filter($replies));
        if ($replies === []) {
            return 'Please tell me the file type you uploaded and the format you want.';
        }

        $reply = $replies[$assistantReplyCount % count($replies)];
        if ($reply === $lastAssistantReply && count($replies) > 1) {
            $reply = $replies[($assistantReplyCount + 1) % count($replies)];
        }

        return $reply;
    }
}
