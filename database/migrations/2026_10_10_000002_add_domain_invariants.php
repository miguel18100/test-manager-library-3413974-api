<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-18 — Bajar a la base las invariantes que hasta ahora solo vivían en el dominio.
 *
 * Fuente única: docs, data-model.md §4 (marcas «pendiente (T-18)»):
 *  - ck_book_total_positive: total_copies >= 1
 *  - ck_user_role:           role en (admin, librarian)
 *
 * Evidencia: un INSERT por psql que viole cualquiera de las dos falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE library.book
            ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1);

        ALTER TABLE library."user"
            ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE library."user" DROP CONSTRAINT IF EXISTS ck_user_role;
        ALTER TABLE library.book DROP CONSTRAINT IF EXISTS ck_book_total_positive;
        SQL);
    }
};
