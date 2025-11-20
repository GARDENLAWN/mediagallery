# GardenLawn MediaGallery - Polecenia Konsoli

Moduł dostarcza poleceń konsoli do synchronizacji mediów z AWS S3 i zarządzania galeriami.

---

## Dostępne Polecenia

### 1. `gardenlawn:mediagallery:sync-s3`

Synchronizuje tabelę `media_gallery_asset` z zawartością bucketa S3.

*   **Podstawowe użycie (tylko dodawanie nowych plików):**
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3
    ```

*   **Opcje:**
    *   `--dry-run`: Wyświetla, jakie zmiany zostałyby wprowadzone, bez modyfikacji bazy danych.
    *   `--with-delete`: **(Ostrożnie!)** Włącza usuwanie z bazy danych wpisów o plikach, które nie istnieją już w S3.
    *   `--force-update`: **(Może być wolne!)** Włącza aktualizację istniejących plików, jeśli brakuje im `hash`, `width`, `height` lub `hash` się zmienił.

    *Przykład pełnej synchronizacji (dodawanie, aktualizacja, usuwanie):*
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3 --with-delete --force-update
    ```

### 2. `gardenlawn:mediagallery:populate-all`

Tworzy galerie na podstawie folderów, linkuje do nich zasoby i opcjonalnie czyści nieużywane galerie. Uruchamiaj **po** `sync-s3`.

*   **Podstawowe użycie (tworzenie galerii i linkowanie):**
    ```sh
    bin/magento gardenlawn:mediagallery:populate-all
    ```

*   **Opcje:**
    *   `--dry-run`: Wyświetla, jakie zmiany zostałyby wprowadzone, bez modyfikacji bazy danych.
    *   `--with-prune`: **(Ostrożnie!)** Włącza usuwanie galerii, które nie odpowiadają już żadnym istniejącym plikom.

    *Przykład użycia z czyszczeniem galerii:*
    ```sh
    bin/magento gardenlawn:mediagallery:populate-all --with-prune
    ```
