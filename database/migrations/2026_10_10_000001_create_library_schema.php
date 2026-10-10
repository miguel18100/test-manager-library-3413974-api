<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-02 — Migración inicial: seis tablas, FK, índices únicos y las cinco categorías sembradas.
 *
 * Fuente única: docs, data-model.md (§3 modelo físico, §4 restricciones, §5 FK, §9 semilla).
 * - Esquema `library`, todas las marcas de tiempo `timestamptz`, ninguna columna con DEFAULT.
 * - Sin created_at/updated_at (data-model §8).
 * - Las reglas marcadas "pendiente (T-18)" NO se crean aquí: las agrega su propia migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE SCHEMA IF NOT EXISTS library;

        -- category: semilla de solo lectura
        CREATE TABLE library.category (
            id   uuid         NOT NULL,
            name varchar(120) NOT NULL,
            CONSTRAINT pk_category PRIMARY KEY (id)
        );
        CREATE UNIQUE INDEX ux_category_name ON library.category (name);

        -- book
        CREATE TABLE library.book (
            id               uuid         NOT NULL,
            title            varchar(250) NOT NULL,
            author           varchar(200) NOT NULL,
            isbn             char(13)     NOT NULL,
            category_id      uuid         NOT NULL,
            total_copies     integer      NOT NULL,
            available_copies integer      NOT NULL,
            cover_key        varchar(512) NULL,
            deleted_at       timestamptz  NULL,
            CONSTRAINT pk_book PRIMARY KEY (id),
            CONSTRAINT fk_book_category FOREIGN KEY (category_id)
                REFERENCES library.category (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
            -- última barrera de concurrencia (ADR-002)
            CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)
        );
        -- ISBN único solo entre libros activos (ADR-003)
        CREATE UNIQUE INDEX ux_book_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL;
        -- acceso Q1: búsqueda por categoría y orden por título, solo activos
        CREATE INDEX ix_book_category_title_active ON library.book (category_id, title) WHERE deleted_at IS NULL;

        -- member
        CREATE TABLE library.member (
            id              uuid         NOT NULL,
            full_name       varchar(200) NOT NULL,
            document_number varchar(30)  NOT NULL,
            CONSTRAINT pk_member PRIMARY KEY (id)
        );
        CREATE UNIQUE INDEX ux_member_document_number ON library.member (document_number);

        -- user (palabra reservada: siempre entrecomillada)
        CREATE TABLE library."user" (
            id            uuid         NOT NULL,
            username      varchar(120) NOT NULL,
            password_hash varchar(512) NOT NULL,
            role          varchar(40)  NOT NULL,
            CONSTRAINT pk_user PRIMARY KEY (id)
        );
        CREATE UNIQUE INDEX ux_user_username ON library."user" (username);

        -- loan
        CREATE TABLE library.loan (
            id                uuid        NOT NULL,
            member_id         uuid        NOT NULL,
            loaned_by_user_id uuid        NOT NULL,
            loaned_at         timestamptz NOT NULL,
            CONSTRAINT pk_loan PRIMARY KEY (id),
            CONSTRAINT fk_loan_member FOREIGN KEY (member_id)
                REFERENCES library.member (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
            CONSTRAINT fk_loan_user FOREIGN KEY (loaned_by_user_id)
                REFERENCES library."user" (id) ON DELETE RESTRICT ON UPDATE NO ACTION
        );
        CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at);

        -- loan_item
        CREATE TABLE library.loan_item (
            id            uuid         NOT NULL,
            loan_id       uuid         NOT NULL,
            book_id       uuid         NOT NULL,
            book_title    varchar(250) NOT NULL,
            isbn          char(13)     NOT NULL,
            category_name varchar(120) NOT NULL, -- congelado, sin FK a propósito (ADR-004)
            due_at        timestamptz  NOT NULL,
            returned_at   timestamptz  NULL,
            CONSTRAINT pk_loan_item PRIMARY KEY (id),
            CONSTRAINT fk_loan_item_loan FOREIGN KEY (loan_id)
                REFERENCES library.loan (id) ON DELETE CASCADE ON UPDATE NO ACTION,
            CONSTRAINT fk_loan_item_book FOREIGN KEY (book_id)
                REFERENCES library.book (id) ON DELETE RESTRICT ON UPDATE NO ACTION
        );
        CREATE UNIQUE INDEX ux_loan_item_loan_book ON library.loan_item (loan_id, book_id);
        CREATE INDEX ix_loan_item_book ON library.loan_item (book_id);
        CREATE INDEX ix_loan_item_due_open ON library.loan_item (due_at) WHERE returned_at IS NULL;

        -- §9 semilla: cinco categorías con identificadores fijos
        INSERT INTO library.category (id, name) VALUES
            ('11111111-1111-4111-8111-111111111111', 'Novela'),
            ('22222222-2222-4222-8222-222222222222', 'Ciencia'),
            ('33333333-3333-4333-8333-333333333333', 'Tecnología'),
            ('44444444-4444-4444-8444-444444444444', 'Historia'),
            ('55555555-5555-4555-8555-555555555555', 'Infantil');
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE;');
    }
};
