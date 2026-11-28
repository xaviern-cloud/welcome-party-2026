<?php

namespace App\Services;

use Illuminate\Support\Str;

class ShowcaseVotingService
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/showcase_voting.json');
    }

    private function defaultState(): array
    {
        return [
            'voting_open' => false,
            'results_revealed' => false,
            'categories' => [
                ['id' => 'perform', 'name' => 'Perform / Talent Show'],
                ['id' => 'fashion_show', 'name' => 'Fashion Show'],
            ],
            'nominees' => [], // ['id', 'category_id', 'name', 'performer_info', 'description', 'photo_url']
            'votes' => [],    // ['perform' => ['092' => 'NOM-xxx'], 'fashion_show' => ['092' => 'NOM-yyy']]
        ];
    }

    public function getState(): array
    {
        if (!file_exists($this->path)) {
            return $this->defaultState();
        }
        $raw = @file_get_contents($this->path);
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded)
            ? array_merge($this->defaultState(), $decoded)
            : $this->defaultState();
    }

    private function mutate(callable $callback): array
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = fopen($this->path, 'c+');
        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $state = json_decode((string) $raw, true);
        if (!is_array($state)) {
            $state = $this->defaultState();
        } else {
            $state = array_merge($this->defaultState(), $state);
        }

        $result = $callback($state);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return ['state' => $state, 'result' => $result];
    }

    public function addCategory(string $name): array
    {
        $clean = trim($name);
        if ($clean === '') {
            throw new \InvalidArgumentException('Nama kategori wajib diisi.');
        }

        $out = $this->mutate(function (array &$state) use ($clean) {
            $slug = Str::slug($clean, '_');
            if ($slug === '') {
                $slug = 'cat_' . strtolower(Str::random(5));
            }
            foreach ($state['categories'] as $c) {
                if ($c['id'] === $slug || strcasecmp($c['name'], $clean) === 0) {
                    throw new \InvalidArgumentException('Kategori tersebut sudah ada.');
                }
            }
            $cat = ['id' => $slug, 'name' => $clean];
            $state['categories'][] = $cat;
            return $cat;
        });

        return $out['result'];
    }

    public function addNominee(array $data): array
    {
        $out = $this->mutate(function (array &$state) use ($data) {
            $catId = (string) ($data['category_id'] ?? '');
            $validCat = collect($state['categories'])->firstWhere('id', $catId);
            if (!$validCat) {
                throw new \InvalidArgumentException('Kategori tidak valid.');
            }

            // Di dalam ShowcaseVotingService::addNominee()
                $nominee = [
                    'id' => 'NOM-' . strtoupper(Str::random(6)),
                    'category_id' => $catId,
                    'name' => trim((string) $data['name']),
                    'angkatan' => trim((string) ($data['angkatan'] ?? '')),
                    'performer_info' => trim((string) ($data['performer_info'] ?? '')),
                    'description' => trim((string) ($data['description'] ?? '')),
                    'photo_url' => trim((string) ($data['photo_url'] ?? '')),
                ];

            $state['nominees'][] = $nominee;
            return $nominee;
        });

        return $out['result'];
    }

    public function deleteNominee(string $nomineeId): void
    {
        $this->mutate(function (array &$state) use ($nomineeId) {
            $state['nominees'] = array_values(array_filter(
                $state['nominees'],
                fn ($n) => ($n['id'] ?? '') !== $nomineeId
            ));

            foreach ($state['votes'] as $catId => $catVotes) {
                if (!is_array($catVotes)) {
                    continue;
                }
                foreach ($catVotes as $nim => $votedId) {
                    if ($votedId === $nomineeId) {
                        unset($state['votes'][$catId][$nim]);
                    }
                }
            }
        });
    }

    public function updateControls(?bool $votingOpen, ?bool $resultsRevealed): array
    {
        $out = $this->mutate(function (array &$state) use ($votingOpen, $resultsRevealed) {
            if ($votingOpen !== null) {
                $state['voting_open'] = $votingOpen;
            }
            if ($resultsRevealed !== null) {
                $state['results_revealed'] = $resultsRevealed;
            }
        });

        return $out['state'];
    }

    public function resetVotes(): array
    {
        $out = $this->mutate(function (array &$state) {
            $state['votes'] = [];
        });

        return $out['state'];
    }

    public function castVote(string $nimCode, string $categoryId, string $nomineeId): void
    {
        $this->mutate(function (array &$state) use ($nimCode, $categoryId, $nomineeId) {
            if (empty($state['voting_open'])) {
                throw new \RuntimeException('Sesi voting belum dibuka atau sudah ditutup oleh panitia.');
            }

            $nominee = collect($state['nominees'])->first(
                fn ($n) => ($n['id'] ?? '') === $nomineeId && ($n['category_id'] ?? '') === $categoryId
            );

            if (!$nominee) {
                throw new \InvalidArgumentException('Peserta nominasi tidak ditemukan pada kategori ini.');
            }

            if (!isset($state['votes'][$categoryId]) || !is_array($state['votes'][$categoryId])) {
                $state['votes'][$categoryId] = [];
            }

            $state['votes'][$categoryId][$nimCode] = $nomineeId;
        });
    }

    public function getAdminOverview(): array
    {
        $state = $this->getState();
        $categories = [];

        foreach ($state['categories'] as $cat) {
            $catId = $cat['id'];
            $catVotes = $state['votes'][$catId] ?? [];
            $counts = array_count_values(array_values($catVotes));
            $totalVotes = count($catVotes);

            $nominees = collect($state['nominees'])
                ->where('category_id', $catId)
                ->map(function ($n) use ($counts, $totalVotes) {
                    $v = (int) ($counts[$n['id']] ?? 0);
                    $n['votes'] = $v;
                    $n['percentage'] = $totalVotes > 0 ? round(($v / $totalVotes) * 100, 1) : 0;
                    return $n;
                })
                ->sortByDesc('votes')
                ->values()
                ->all();

            $categories[] = [
                'id' => $catId,
                'name' => $cat['name'],
                'total_votes' => $totalVotes,
                'nominees' => $nominees,
            ];
        }

        return [
            'voting_open' => (bool) $state['voting_open'],
            'results_revealed' => (bool) $state['results_revealed'],
            'categories' => $categories,
        ];
    }

    public function getParticipantPayload(string $nimCode): array
    {
        $state = $this->getState();
        $revealed = (bool) $state['results_revealed'];
        $categories = [];

        foreach ($state['categories'] as $cat) {
            $catId = $cat['id'];
            $catVotes = $state['votes'][$catId] ?? [];
            $myVote = $catVotes[$nimCode] ?? null;
            $counts = $revealed ? array_count_values(array_values($catVotes)) : [];
            $totalVotes = $revealed ? count($catVotes) : 0;

            $nomineesQuery = collect($state['nominees'])->where('category_id', $catId);

            $nominees = $nomineesQuery->map(function ($n) use ($revealed, $counts, $totalVotes) {
                $item = [
                        'id' => $n['id'],
                        'category_id' => $n['category_id'],
                        'name' => $n['name'],
                        'angkatan' => $n['angkatan'] ?? '',
                        'performer_info' => $n['performer_info'] ?? '',
                        'description' => $n['description'] ?? '',
                        'photo_url' => $n['photo_url'] ?? '',
                    ];
                if ($revealed) {
                    $v = (int) ($counts[$n['id']] ?? 0);
                    $item['votes'] = $v;
                    $item['percentage'] = $totalVotes > 0 ? round(($v / $totalVotes) * 100, 1) : 0;
                }
                return $item;
            });

            if ($revealed) {
                $nominees = $nominees->sortByDesc('votes');
            }

            $categories[] = [
                'id' => $catId,
                'name' => $cat['name'],
                'my_vote' => $myVote,
                'total_votes' => $revealed ? $totalVotes : null,
                'nominees' => $nominees->values()->all(),
            ];
        }

        return [
            'voting_open' => (bool) $state['voting_open'],
            'results_revealed' => $revealed,
            'categories' => $categories,
        ];
    }
}
