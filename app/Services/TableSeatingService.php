<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\User;

class TableSeatingService
{
    public const KEY_SEATING = 'event_table_seating';
    public const KEY_SESSION = 'table_spokesperson_session';

    public static function getSeating(): array
    {
        $raw  = Setting::getValue(self::KEY_SEATING, '{"tables":[]}');
        $data = json_decode((string) $raw, true);

        return [
            'tables' => is_array($data['tables'] ?? null) ? array_values($data['tables']) : [],
        ];
    }

    public static function saveSeating(array $tables): void
    {
        $clean    = [];
        $usedNims = [];

        foreach ($tables as $idx => $t) {
            $name = trim((string) ($t['name'] ?? ('Meja '.($idx + 1))));
            if ($name === '') {
                $name = 'Meja '.($idx + 1);
            }
            $id      = trim((string) ($t['id'] ?? ('TBL-'.($idx + 1))));
            $members = [];

            foreach (($t['members'] ?? []) as $m) {
                $nim = User::normalizeCode((string) $m);
                if ($nim !== '' && ! isset($usedNims[$nim])) {
                    $usedNims[$nim] = true;
                    $members[]      = $nim;
                }
            }

            $clean[] = [
                'id'      => $id,
                'name'    => $name,
                'members' => $members,
            ];
        }

        Setting::putValue(self::KEY_SEATING, json_encode(['tables' => $clean], JSON_UNESCAPED_UNICODE));
    }

    public static function getSession(): array
    {
        $raw  = Setting::getValue(self::KEY_SESSION, '{}');
        $data = json_decode((string) $raw, true);

        return array_merge([
            'status'      => 'idle', // idle | open | closed
            'votes'       => [],     // ['voter_nim' => 'candidate_nim']
            'transferred' => [],     // ['sender_nim' => true]
            'updated_at'  => null,
        ], is_array($data) ? $data : []);
    }

    public static function saveSession(array $session): void
    {
        $session['updated_at'] = now()->toIso8601String();
        Setting::putValue(self::KEY_SESSION, json_encode($session, JSON_UNESCAPED_UNICODE));
    }

    public static function controlSession(string $action): array
    {
        $session = self::getSession();

        if ($action === 'start') {
            $session = [
                'status'      => 'open',
                'votes'       => [],
                'transferred' => [],
            ];
        } elseif ($action === 'close') {
            $session['status'] = 'closed';
        } elseif ($action === 'reopen') {
            $session['status'] = 'open';
        } elseif ($action === 'reset') {
            $session = [
                'status'      => 'idle',
                'votes'       => [],
                'transferred' => [],
            ];
        }

        self::saveSession($session);

        return $session;
    }

    public static function castVote(string $voterNim, string $candidateNim): array
    {
        $voterNim     = User::normalizeCode($voterNim);
        $candidateNim = User::normalizeCode($candidateNim);

        $session = self::getSession();
        if (($session['status'] ?? 'idle') !== 'open') {
            throw new BusinessRuleException('Sesi voting Juru Bicara Meja sedang tidak dibuka.');
        }

        $table = self::findTableForNim($voterNim);
        if (! $table) {
            throw new BusinessRuleException('Anda belum terdaftar di meja mana pun. Hubungi panitia.');
        }

        if (! in_array($candidateNim, $table['members'], true)) {
            throw new BusinessRuleException('Kandidat juru bicara harus berasal dari meja yang sama.');
        }

        if (! empty($session['transferred'][$voterNim])) {
            throw new BusinessRuleException('Anda sudah mentransfer saldo, pilihan voting tidak dapat diubah.');
        }

        $session['votes'][$voterNim] = $candidateNim;
        self::saveSession($session);

        return $session;
    }

    public static function markTransferred(string $senderNim): void
    {
        $senderNim                          = User::normalizeCode($senderNim);
        $session                            = self::getSession();
        $session['transferred'][$senderNim] = true;
        self::saveSession($session);
    }

    public static function findTableForNim(string $nim): ?array
    {
        $nim     = User::normalizeCode($nim);
        $seating = self::getSeating();

        foreach ($seating['tables'] as $t) {
            if (in_array($nim, $t['members'] ?? [], true)) {
                return $t;
            }
        }

        return null;
    }

    public static function participantTableState(User $me): array
    {
        $session = self::getSession();
        $status  = $session['status'] ?? 'idle';
        $table   = self::findTableForNim($me->nim_code);

        if (! $table) {
            return [
                'session_status' => $status,
                'has_table'      => false,
                'catalog_locked' => false,
            ];
        }

        $memberNims = $table['members'] ?? [];
        $users      = User::participants()
            ->whereIn('nim_code', $memberNims)
            ->get(['nim_code', 'name', 'group_name', 'is_checked_in', 'balance'])
            ->keyBy('nim_code');

        $votes       = $session['votes'] ?? [];
        $transferred = $session['transferred'] ?? [];

        $tally = [];
        foreach ($memberNims as $mNim) {
            $tally[$mNim] = 0;
        }

        $activeMembersCount = 0;
        $votesCastCount     = 0;

        foreach ($memberNims as $mNim) {
            $u = $users->get($mNim);
            if ($u && $u->is_checked_in) {
                $activeMembersCount++;
            }
            $votedFor = $votes[$mNim] ?? null;
            if ($votedFor && isset($tally[$votedFor])) {
                $tally[$votedFor]++;
                $votesCastCount++;
            }
        }

        $spokespersonNim = null;
        $maxVotes        = 0;
        foreach ($memberNims as $mNim) {
            $v = $tally[$mNim] ?? 0;
            if ($v > $maxVotes) {
                $maxVotes        = $v;
                $spokespersonNim = $mNim;
            }
        }

        $minRequiredVotes = max(1, $activeMembersCount);
        $votingComplete   = ($spokespersonNim !== null) && ($votesCastCount >= $minRequiredVotes);

        $membersPayload                = [];
        $allNonSpokespersonTransferred = true;

        foreach ($memberNims as $mNim) {
            $u = $users->get($mNim);
            if (! $u) {
                continue;
            }
            $isSpokes       = $votingComplete && ($spokespersonNim === $mNim);
            $hasTransferred = ! empty($transferred[$mNim]) || ((int) $u->balance === 0);

            if ($u->is_checked_in && ! $isSpokes && ! $hasTransferred) {
                $allNonSpokespersonTransferred = false;
            }

            $membersPayload[] = [
                'nim_code'        => $u->nim_code,
                'name'            => $u->name,
                'group_name'      => $u->group_name,
                'is_checked_in'   => (bool) $u->is_checked_in,
                'votes'           => $tally[$mNim] ?? 0,
                'voted_for'       => $votes[$mNim] ?? null,
                'is_spokesperson' => $isSpokes,
                'has_transferred' => $hasTransferred,
            ];
        }

        $spokespersonUser = $spokespersonNim ? $users->get($spokespersonNim) : null;
        $iAmSpokesperson  = $votingComplete && ($spokespersonNim === $me->nim_code);
        $iHaveTransferred = ! empty($transferred[$me->nim_code]) || ((int) $me->balance === 0);

        $catalogLocked = ($status === 'closed') && ! $iAmSpokesperson && ! $iHaveTransferred && ((int) $me->balance > 0);

        return [
            'session_status'     => $status,
            'has_table'          => true,
            'table_id'           => $table['id'],
            'table_name'         => $table['name'],
            'members'            => $membersPayload,
            'my_vote'            => $votes[$me->nim_code] ?? null,
            'votes_cast'         => $votesCastCount,
            'votes_required'     => $minRequiredVotes,
            'voting_complete'    => $votingComplete,
            'spokesperson_nim'   => $votingComplete ? $spokespersonNim : null,
            'spokesperson_name'  => $votingComplete ? ($spokespersonUser?->name ?? $spokespersonNim) : null,
            'i_am_spokesperson'  => $iAmSpokesperson,
            'i_have_transferred' => $iHaveTransferred,
            'my_balance'         => (int) $me->balance,
            'table_finalized'    => $votingComplete && $allNonSpokespersonTransferred,
            'catalog_locked'     => $catalogLocked,
        ];
    }

    public static function adminOverview(): array
    {
        $seating     = self::getSeating();
        $session     = self::getSession();
        $status      = $session['status'] ?? 'idle';
        $votes       = $session['votes'] ?? [];
        $transferred = $session['transferred'] ?? [];

        $allNims = [];
        foreach ($seating['tables'] as $t) {
            foreach (($t['members'] ?? []) as $m) {
                $allNims[] = $m;
            }
        }

        $users = User::participants()
            ->whereIn('nim_code', array_unique($allNims))
            ->get(['nim_code', 'name', 'group_name', 'is_checked_in', 'balance'])
            ->keyBy('nim_code');

        $tablesSummary    = [];
        $unfinalizedCount = 0;

        foreach ($seating['tables'] as $t) {
            $memberNims  = $t['members'] ?? [];
            $tally       = array_fill_keys($memberNims, 0);
            $activeCount = 0;
            $votesCount  = 0;

            foreach ($memberNims as $mNim) {
                $u = $users->get($mNim);
                if ($u && $u->is_checked_in) {
                    $activeCount++;
                }
                $vf = $votes[$mNim] ?? null;
                if ($vf && isset($tally[$vf])) {
                    $tally[$vf]++;
                    $votesCount++;
                }
            }

            $spokesNim = null;
            $maxVotes  = 0;
            foreach ($memberNims as $mNim) {
                if (($tally[$mNim] ?? 0) > $maxVotes) {
                    $maxVotes  = $tally[$mNim];
                    $spokesNim = $mNim;
                }
            }

            $minReq     = max(1, $activeCount);
            $votingDone = ($spokesNim !== null) && ($votesCount >= $minReq);

            $membersDetail  = [];
            $pendingMembers = [];
            foreach ($memberNims as $mNim) {
                $u = $users->get($mNim);
                if (! $u) {
                    continue;
                }
                $isSpokes = $votingDone && ($spokesNim === $mNim);
                $doneTx   = ! empty($transferred[$mNim]) || ((int) $u->balance === 0);

                if ($u->is_checked_in && ! $isSpokes && ! $doneTx) {
                    $pendingMembers[] = "{$u->name} (#{$u->nim_code})";
                }

                $membersDetail[] = [
                    'nim_code'        => $u->nim_code,
                    'name'            => $u->name,
                    'is_checked_in'   => (bool) $u->is_checked_in,
                    'balance'         => (int) $u->balance,
                    'votes'           => $tally[$mNim] ?? 0,
                    'is_spokesperson' => $isSpokes,
                    'has_transferred' => $doneTx,
                ];
            }

            $isFinalized = $votingDone && count($pendingMembers) === 0;
            if (! $isFinalized && count($memberNims) > 0) {
                $unfinalizedCount++;
            }

            $spokesUser = $spokesNim ? $users->get($spokesNim) : null;

            $tablesSummary[] = [
                'id'                => $t['id'],
                'name'              => $t['name'],
                'member_count'      => count($membersDetail),
                'active_count'      => $activeCount,
                'votes_cast'        => $votesCount,
                'votes_required'    => $minReq,
                'voting_done'       => $votingDone,
                'spokesperson_nim'  => $votingDone ? $spokesNim : null,
                'spokesperson_name' => $votingDone ? ($spokesUser?->name ?? $spokesNim) : null,
                'spokesperson_bal'  => $votingDone && $spokesUser ? (int) $spokesUser->balance : 0,
                'is_finalized'      => $isFinalized,
                'pending_members'   => $pendingMembers,
                'members'           => $membersDetail,
            ];
        }

        return [
            'status'            => $status,
            'total_tables'      => count($tablesSummary),
            'unfinalized_count' => $unfinalizedCount,
            'tables'            => $tablesSummary,
        ];
    }
}
