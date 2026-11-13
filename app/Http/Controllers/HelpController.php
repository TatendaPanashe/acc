<?php

namespace App\Http\Controllers;

use App\Services\Authorization\RoutePermissions;
use App\Services\Help\HelpCentre;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Help Centre: guides from the user manual, PDF downloads, setup checklist and import templates. */
class HelpController extends Controller
{
    public function __construct(private readonly HelpCentre $help)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('help.index', [
            'categories' => $this->help->categories($user),
            'startHere' => collect(config('help.start_here'))->map(fn ($n) => $this->help->byNumber($n, $user))->filter()->values(),
            'templates' => config('help.templates'),
            'guideCount' => $this->help->visibleTo($user)->count(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $guides = $this->help->visibleTo($request->user())->values();
        $index = $guides->search(fn ($g) => $g['slug'] === $slug);
        abort_if($index === false, 404);
        $guide = $guides[$index];

        return view('help.show', [
            'guide' => $guide,
            'html' => $this->help->html($guide),
            'category' => config('help.categories.'.$guide['category']),
            'previous' => $guides[$index - 1] ?? null,
            'next' => $guides[$index + 1] ?? null,
            'screen' => $this->screenFor($request, $guide['number']),
        ]);
    }

    public function pdf(Request $request, string $slug): Response
    {
        $guide = $this->help->visibleTo($request->user())->get($slug);
        abort_unless($guide, 404);

        return $this->renderPdf([$guide], $guide['number'].'. '.$guide['title'], 'acc263-guide-'.$guide['slug'].'.pdf');
    }

    public function manual(Request $request): Response
    {
        return $this->renderPdf($this->help->visibleTo($request->user())->values()->all(), 'User manual', 'acc263-user-manual.pdf', withContents: true);
    }

    public function checklist(): Response
    {
        return Pdf::loadView('help.checklist-pdf')->setPaper('a4', 'portrait')->download('acc263-setup-checklist.pdf');
    }

    public function template(string $key): BinaryFileResponse
    {
        $template = config('help.templates.'.$key);
        abort_unless($template, 404);

        return response()->download(resource_path('help/'.$template[2]), basename($template[2]), ['Content-Type' => 'text/csv']);
    }

    private function renderPdf(array $guides, string $title, string $filename, bool $withContents = false): Response
    {
        $sections = array_map(fn ($g) => $g + ['html' => $this->help->html($g)], $guides);

        return Pdf::loadView('help.pdf', compact('sections', 'title', 'withContents'))->setPaper('a4', 'portrait')->download($filename);
    }

    /** "Open the screen" button when the user may open that screen. */
    private function screenFor(Request $request, string $number): ?array
    {
        [$label, $route] = config('help.screens.'.$number) ?? [null, null];
        if (!$route || !Route::has($route)) {
            return null;
        }
        $user = $request->user();
        if (str_starts_with($route, 'system.')) {
            return $user->is_superuser ? ['label' => $label, 'url' => route($route)] : null;
        }

        return app(RoutePermissions::class)->allows($user, $route) ? ['label' => $label, 'url' => route($route)] : null;
    }
}
