<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

function knowledgeBaseContentAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

/**
 * @return array<int, array{slug: string, title: string, path: string}>
 */
function knowledgeBaseArticles(): array
{
    $root = base_path('docs/knowledge-base/fr');
    $articles = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'md') {
            continue;
        }

        $content = File::get($file->getPathname());

        if (preg_match('/^type:\s*group/m', $content) === 1) {
            continue;
        }

        $slug = str_replace([$root.DIRECTORY_SEPARATOR, '.md'], '', $file->getPathname());
        $slug = str_replace(DIRECTORY_SEPARATOR, '/', $slug);

        preg_match('/^title:\s*(.+)$/m', $content, $matches);

        $articles[$slug] = [
            'slug' => $slug,
            'title' => trim($matches[1] ?? $slug),
            'path' => $file->getPathname(),
        ];
    }

    ksort($articles);

    return array_values($articles);
}

test('tous les articles de la base documentaire sont accessibles à un administrateur', function (): void {
    $this->actingAs(knowledgeBaseContentAdmin());

    $articles = knowledgeBaseArticles();

    expect($articles)->not->toBeEmpty();

    foreach ($articles as $article) {
        $this->get('/aide/'.$article['slug'])
            ->assertOk()
            ->assertSee($article['title']);
    }
});

test('les liens relatifs des articles pointent vers des fichiers existants', function (): void {
    $checked = 0;

    foreach (knowledgeBaseArticles() as $article) {
        $content = File::get($article['path']);

        preg_match_all('/\]\(([^)]+)\)/', $content, $matches);

        foreach ($matches[1] as $target) {
            if (str_starts_with($target, '#') || preg_match('~^(https?:|mailto:)~', $target) === 1) {
                continue;
            }

            $path = str_starts_with($target, '/')
                ? base_path('docs/knowledge-base/fr'.ltrim($target, '/'))
                : dirname($article['path']).DIRECTORY_SEPARATOR.$target;

            $checked++;

            expect(is_file($path))->toBeTrue("Lien cassé dans {$article['slug']} : {$target}");
        }
    }

    expect($checked)->toBeGreaterThanOrEqual(0);
});

test('les images référencées par les articles existent', function (): void {
    $checked = 0;

    foreach (knowledgeBaseArticles() as $article) {
        $content = File::get($article['path']);

        preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $content, $matches);

        foreach ($matches[1] as $target) {
            if (preg_match('#^https?:#', $target) === 1) {
                continue;
            }

            $path = str_starts_with($target, '/')
                ? public_path(ltrim($target, '/'))
                : dirname($article['path']).DIRECTORY_SEPARATOR.$target;

            $checked++;

            expect(is_file($path))->toBeTrue("Image absente dans {$article['slug']} : {$target}");
        }
    }

    expect($checked)->toBeGreaterThanOrEqual(0);
});

test('les articles ne contiennent pas de secret ni de jeton', function (): void {
    foreach (knowledgeBaseArticles() as $article) {
        $content = File::get($article['path']);

        expect($content)->not->toMatch('/(password|mot de passe)\s*[:=]\s*\S+/i')
            ->and($content)->not->toContain('BACKUP_ARCHIVE_PASSWORD=')
            ->and($content)->not->toContain('CALENDAR_TOKEN');
    }
});
