<?php

namespace App\Http\Controllers;

use App\Services\ConversionHistoryService;
use App\Services\UserAccountService;
use App\Support\AssetVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MyFilesController extends Controller
{
    public function index(ConversionHistoryService $historyService, UserAccountService $accounts): View|RedirectResponse
    {
        $currentUser = session('auth_user');
        if (! $currentUser) {
            return redirect()->route('signin');
        }

        $storedAccount = $accounts->findForSession($currentUser);
        if ($storedAccount) {
            $currentUser = $accounts->sessionUser($storedAccount);
            session(['auth_user' => $currentUser]);
        }

        return view('my-files', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => $currentUser,
            'files' => $historyService->list($currentUser),
        ]);
    }
}
