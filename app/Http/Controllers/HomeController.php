<?php

namespace App\Http\Controllers;

use App\Services\ConversionService;
use App\Services\AiSupportService;
use App\Services\UserAccountService;
use App\Support\AssetVersion;
use App\Support\FluxContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(UserAccountService $accounts): View
    {
        $currentUser = $this->currentUser($accounts);

        return view('index', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => $currentUser,
        ]);
    }

    public function whatsapp(UserAccountService $accounts): View
    {
        $currentUser = $this->currentUser($accounts);
        $number = preg_replace('/\D+/', '', (string) config('services.whatsapp.number'));
        $message = rawurlencode(config('services.whatsapp.message', 'Hello, I need help with file conversion.'));

        return view('whatsapp', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => $currentUser,
            'whatsappNumber' => $number,
            'whatsappAppUrl' => $number ? "whatsapp://send?phone={$number}&text={$message}" : "whatsapp://send?text={$message}",
            'whatsappWebUrl' => $number ? "https://web.whatsapp.com/send?phone={$number}&text={$message}" : "https://web.whatsapp.com/send?text={$message}",
            'whatsappDownloadUrl' => 'https://www.whatsapp.com/download',
        ]);
    }

    public function highlights(ConversionService $conversionService): JsonResponse
    {
        $payload = Cache::remember('home.highlights.v4', now()->addHours(6), fn (): array => [
            'stats' => [
                ['value' => '200+', 'label' => 'Supported formats'],
                ['value' => '99.9%', 'label' => 'Platform uptime'],
                ['value' => '< 2 min', 'label' => 'Typical turnaround'],
            ],
            'tools' => FluxContent::tools(),
            'tool_groups' => FluxContent::toolGroups(),
            'format_groups' => FluxContent::formatGroups(),
            'format_descriptions' => FluxContent::formatDescriptions(),
            'supported_conversion_map' => $conversionService->supportedConversionMap(),
            'conversion_support_summary' => $conversionService->supportSummary(FluxContent::formatGroups()),
            'steps' => FluxContent::steps(),
            'pricing' => FluxContent::pricing(),
        ]);

        return response()
            ->json($payload)
            ->setPublic()
            ->setMaxAge(3600);
    }

    public function aiChat(Request $request, AiSupportService $aiSupport): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['nullable', 'in:user,assistant'],
            'history.*.content' => ['nullable', 'string', 'max:1200'],
        ]);

        return response()->json([
            'reply' => $aiSupport->reply($validated['message'], $validated['history'] ?? []),
        ]);
    }

    private function currentUser(UserAccountService $accounts): ?array
    {
        $currentUser = session('auth_user');
        if ($currentUser && ($storedAccount = $accounts->findForSession($currentUser))) {
            $currentUser = $accounts->sessionUser($storedAccount);
            session(['auth_user' => $currentUser]);
        }

        return $currentUser;
    }
}
