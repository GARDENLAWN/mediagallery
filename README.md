# GardenLawn MediaGallery - Polecenia Konsoli

Moduł dostarcza poleceń konsoli do synchronizacji mediów z AWS S3 i zarządzania galeriami.

---

## Dostępne Polecenia

### 1. `gardenlawn:gallery:convert-to-webp`

Kompleksowe narzędzie do konwersji obrazów na format WebP, tworzenia miniaturek i czyszczenia.

*   **Kolejność operacji:**
    1.  **Czyszczenie wstępne:** Skanuje cały bucket w poszukiwaniu i usuwa niepoprawnie nazwane pliki, np. `obraz.jpg.webp` lub `obraz.webp.webp`.
    2.  **Konwersja obrazów:** Wyszukuje obrazy (JPG, PNG, JPEG) w `pub/media/` (z wyłączeniem `catalog/` i `tmp/`) i konwertuje je do formatu WebP.
    3.  **Tworzenie miniaturek:** Dla każdego nowo utworzonego pliku `.webp` generuje jego miniaturkę (domyślnie 240x240px) i zapisuje ją w odpowiednim katalogu `.thumbs`, np. `pub/media/.thumbswysiwyg/obraz.webp`.
    4.  **Czyszczenie końcowe:** Usuwa całą zawartość folderu `pub/media/tmp/` w S3, a następnie tworzy go na nowo jako pusty katalog.

*   **Podstawowe użycie:**
    ```sh
    bin/magento gardenlawn:gallery:convert-to-webp
    ```

*   **Opcje:**
    *   `--force` lub `-f`: Wymusza regenerację istniejących już plików `.webp` i ich miniaturek. Skrypt najpierw usunie stare pliki, a następnie utworzy je na nowo.
        ```sh
        bin/magento gardenlawn:gallery:convert-to-webp --force
        ```
    *   `-v`: Tryb szczegółowy (verbose). Wyświetla szczegółowe logi z każdego etapu (pobieranie, konwersja, wysyłanie, czyszczenie).
        ```sh
        bin/magento gardenlawn:gallery:convert-to-webp -v
        ```

    Zaleca się uruchamianie komendy w tle lub w sesji `screen` dla dużych bibliotek mediów.

### 2. `gardenlawn:mediagallery:sync-s3`

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
    bin/magento gardenlawn:mediagallery:sync-s3 --with-delete --force-update --dry-run
    ```

### 3. `gardenlawn:mediagallery:populate-all`

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
    bin/magento gardenlawn:mediagallery:populate-all --with-prune --dry-run
    ```

### 4. `gardenlawn:mediagallery:deduplicate-assets`

Wyszukuje i usuwa zduplikowane zasoby (pliki o tej samej ścieżce) z tabeli `media_gallery_asset`.

*   **Działanie:**
    1.  Znajduje wszystkie ścieżki plików, które mają więcej niż jeden wpis w bazie.
    2.  Dla każdej zduplikowanej ścieżki zachowuje najstarszy wpis (o najniższym `id`).
    3.  Aktualizuje wszystkie powiązania w tabeli `gardenlawn_mediagallery_asset_link`, aby wskazywały na zachowany wpis.
    4.  Usuwa pozostałe, zduplikowane wpisy z tabeli `media_gallery_asset`.

*   **Podstawowe użycie:**
    ```sh
    bin/magento gardenlawn:mediagallery:deduplicate-assets
    ```

*   **Opcje:**
    *   `--dry-run`: Wyświetla, które zasoby zostałyby usunięte, bez wprowadzania zmian w bazie danych. Zalecane do uruchomienia przed właściwą operacją.
        ```sh
        bin/magento gardenlawn:mediagallery:deduplicate-assets --dry-run
        ```
