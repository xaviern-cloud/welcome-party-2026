<?php

namespace App\Http\Controllers;

use App\Services\ShowcaseVotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ShowcaseVotingController extends Controller
{
    public function __construct(private ShowcaseVotingService $voting) {}

    public function adminIndex(): View
    {
        return view('admin.showcase-voting', [
            'overview' => $this->voting->getAdminOverview(),
        ]);
    }

    public function adminStatus(): JsonResponse
    {
        return response()->json($this->voting->getAdminOverview());
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        try {
            $this->voting->addCategory($data['name']);
            return back()->with('success', 'Kategori baru "' . $data['name'] . '" berhasil ditambahkan.');
        } catch (\Throwable $e) {
            return back()->withErrors(['category' => $e->getMessage()]);
        }
    }

    public function storeNominee(Request $request): RedirectResponse
    {
        // Di ShowcaseVotingController::storeNominee()
        $data = $request->validate([
            'category_id' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],
            'angkatan' => ['nullable', 'string', 'max:30'],
            'performer_info' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'photo_url' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->voting->addNominee($data);
            return back()->with('success', 'Peserta "' . $data['name'] . '" berhasil ditambahkan ke daftar voting.');
        } catch (\Throwable $e) {
            return back()->withErrors(['nominee' => $e->getMessage()]);
        }
    }

    public function destroyNominee(string $id): RedirectResponse
    {
        $this->voting->deleteNominee($id);
        return back()->with('success', 'Peserta nominasi berhasil dihapus.');
    }

    public function control(Request $request): JsonResponse
    {
        $data = $request->validate([
            'voting_open' => ['nullable', 'boolean'],
            'results_revealed' => ['nullable', 'boolean'],
        ]);

        $this->voting->updateControls(
            array_key_exists('voting_open', $data) ? (bool) $data['voting_open'] : null,
            array_key_exists('results_revealed', $data) ? (bool) $data['results_revealed'] : null
        );

        return response()->json([
            'message' => 'Pengaturan voting berhasil diperbarui.',
            'overview' => $this->voting->getAdminOverview(),
        ]);
    }

    public function resetVotes(): RedirectResponse
    {
        $this->voting->resetVotes();
        return back()->with('success', 'Seluruh perolehan suara voting telah direset menjadi 0.');
    }

    public function exportExcel(): Response
    {
        $overview = $this->voting->getAdminOverview();
        $rows = '';

        foreach ($overview['categories'] as $cat) {
            $rank = 1;
            foreach ($cat['nominees'] as $n) {
                // Di ShowcaseVotingController::exportExcel() bagian baris $rows
            $rows .= '<tr>'
                . '<td>' . e($cat['name']) . '</td>'
                . '<td align="center">' . $rank++ . '</td>'
                . '<td>' . e($n['name']) . '</td>'
                . '<td align="center">' . e($n['angkatan'] ?? '-') . '</td>'
                . '<td>' . e($n['performer_info'] ?: '-') . '</td>'
                . '<td align="right">' . (int) $n['votes'] . '</td>'
                . '<td align="right">' . $n['percentage'] . '%</td>'
                . '</tr>';
            }
        }

        $html = '<html><head><meta charset="UTF-8"></head><body>'
            . '<table border="1">'
            . '<thead><tr style="background:#D4AF37;font-weight:bold;">'
            . '<th>Kategori</th><th>Peringkat</th><th>Nama Penampil / Peserta</th><th>Angkatan / Kelompok</th><th>Jumlah Suara</th><th>Persentase</th>'
            . '</tr></thead><tbody>'
            . ($rows ?: '<tr><td colspan="6">Belum ada data nominasi</td></tr>')
            . '</tbody></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hasil_voting_perform_fashionshow_' . now()->format('Ymd_His') . '.xls"',
        ]);
    }

    public function participantIndex(Request $request): View
    {
        $user = $request->user();

        return view('participant.voting', [
            'payload' => $this->voting->getParticipantPayload($user->nim_code),
            'stateUrl' => route('participant.voting.state'),
            'voteUrl' => route('participant.voting.cast'),
        ]);
    }

    public function participantState(Request $request): JsonResponse
    {
        return response()->json(
            $this->voting->getParticipantPayload($request->user()->nim_code)
        );
    }

    public function castVote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'string', 'max:50'],
            'nominee_id' => ['required', 'string', 'max:50'],
        ]);

        $user = $request->user();

        try {
            $this->voting->castVote($user->nim_code, $data['category_id'], $data['nominee_id']);
            return response()->json([
                'message' => 'Suara Anda berhasil disimpan!',
                'payload' => $this->voting->getParticipantPayload($user->nim_code),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
