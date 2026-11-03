<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('catatans', 'show_preview')) {
    Schema::table('catatans', function (Blueprint $table) {
        $table->boolean('show_preview')->default(false)->after('preview_isi');
    });
}

      if (! Schema::hasTable('catatan_search_tokens')) {
    Schema::create('catatan_search_tokens', function (Blueprint $table) {
        $table->id();
        $table->foreignId('catatan_id')->constrained('catatans')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('token_hash', 64);

        $table->unique(
            ['catatan_id', 'token_hash'],
            'catatan_search_tokens_catatan_hash_unique'
        );

        $table->index(
            ['user_id', 'token_hash'],
            'catatan_search_tokens_user_hash_index'
        );
    });
}
        DB::table('catatans')
            ->select(['id', 'user_id', 'isi'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $plainText = $this->plainText((string) $row->isi);

                    DB::table('catatans')
                        ->where('id', $row->id)
                        ->update([
                            'isi' => Crypt::encryptString($plainText),
                            'preview_isi' => '',
                        ]);

                    $hashes = array_map(
                        fn (string $token): string => $this->tokenHash($token),
                        $this->tokens($plainText)
                    );

                    if ($hashes === []) {
                        continue;
                    }

                    DB::table('catatan_search_tokens')->insertOrIgnore(array_map(
                        fn (string $hash): array => [
                            'catatan_id' => $row->id,
                            'user_id' => $row->user_id,
                            'token_hash' => $hash,
                        ],
                        $hashes
                    ));
                }
            });
    }

    public function down(): void
    {
        DB::table('catatans')
            ->select(['id', 'isi'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $plainText = $this->plainText((string) $row->isi);

                    DB::table('catatans')
                        ->where('id', $row->id)
                        ->update([
                            'isi' => $plainText,
                            'preview_isi' => $this->preview($plainText),
                        ]);
                }
            });

        Schema::dropIfExists('catatan_search_tokens');

        Schema::table('catatans', function (Blueprint $table) {
            $table->dropColumn('show_preview');
        });
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $normalized = Str::of(strip_tags($text))
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();

        if ($normalized === '') {
            return [];
        }

        $stopWords = array_flip([
            'ada',
            'akan',
            'atau',
            'dan',
            'dari',
            'dengan',
            'di',
            'ini',
            'itu',
            'ke',
            'kita',
            'pada',
            'saya',
            'sebagai',
            'untuk',
            'yang',
        ]);

        $tokens = [];
        foreach (explode(' ', $normalized) as $token) {
            $token = trim($token);
            $length = mb_strlen($token);

            if ($length < 3 || $length > 64 || isset($stopWords[$token])) {
                continue;
            }

            $tokens[$token] = true;
            if (count($tokens) >= 200) {
                break;
            }
        }

        return array_keys($tokens);
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }

    private function preview(string $text): string
    {
        return Str::limit(Str::squish(strip_tags($text)), 97, '');
    }
};
