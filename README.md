# Moduł: GardenLawn MediaGallery

Moduł ten rozszerza standardową funkcjonalność galerii mediów w Magento 2, automatyzując zarządzanie zasobami (obrazami, wideo) przechowywanymi w usłudze AWS S3. Główne zadania modułu to synchronizacja bazy danych Magento z zawartością bucketa S3 oraz automatyczne tworzenie galerii i przypisywanie do nich zasobów na podstawie struktury folderów.

## Główne Funkcjonalności

*   **Synchronizacja z S3:** Automatycznie dodaje do bazy danych Magento (`media_gallery_asset`) wpisy dla nowych plików, które pojawiły się w S3.
*   **Czyszczenie Bazy Danych:** Opcjonalnie usuwa z bazy danych wpisy, które nie mają już odpowiedników w S3.
*   **Automatyczne Tworzenie Galerii:** Tworzy galerie na podstawie ścieżek folderów zasobów (np. plik `produkty/kosze/model-x.jpg` przyczyni się do stworzenia galerii `produkty` i `produkty/kosze`).
*   **Automatyczne Linkowanie Zasobów:** Przypisuje zasoby do odpowiednich galerii.
*   **Pełna Automatyzacja:** Dwa zadania cron zapewniają, że synchronizacja i linkowanie odbywają się automatycznie w tle.
*   **Ręczna Kontrola:** Polecenia konsoli pozwalają na pełną kontrolę i ręczne uruchomienie każdego procesu.

---

## Polecenia Konsoli

### 1. Synchronizacja z S3

To polecenie służy do synchronizacji tabeli `media_gallery_asset` z zawartością skonfigurowanego bucketa S3.

**Podstawowe użycie (tylko dodawanie nowych plików):**
```sh
bin/magento gardenlawn:mediagallery:sync-s3
```

**Opcje:**

*   `--dry-run`
    Uruchamia polecenie w trybie "na sucho". Wyświetla, jakie zasoby zostałyby dodane lub usunięte, ale nie wprowadza żadnych zmian w bazie danych.
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3 --dry-run --with-delete
    ```

*   `--with-delete`
    **Używać z ostrożnością!** Włącza mechanizm usuwania. Polecenie usunie z bazy danych wpisy o zasobach, które nie zostały znalezione w S3.
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3 --with-delete
    ```

### 2. Tworzenie Galerii i Linkowanie Zasobów

To polecenie wykonuje dwie czynności:
1.  Tworzy nowe galerie na podstawie ścieżek zasobów, które istnieją już w bazie danych.
2.  Linkuje zasoby do odpowiednich galerii.

**Użycie:**
```sh
bin/magento gardenlawn:mediagallery:populate-all
```

**Opcje:**

*   `--dry-run`
    Wyświetla, jakie galerie i powiązania zostałyby utworzone, bez modyfikowania bazy danych.

---

## Zadania Cron (Automatyzacja)

Moduł konfiguruje dwa zadania cron, które automatyzują cały proces:

1.  **`gardenlawn_mediagallery_s3_sync`**
    *   **Harmonogram:** Codziennie o 3:00 w nocy.
    *   **Akcja:** Synchronizuje zasoby z S3 (odpowiednik `bin/magento gardenlawn:mediagallery:sync-s3` **bez** opcji usuwania).

2.  **`gardenlawn_mediagallery_link_assets`**
    *   **Harmonogram:** Codziennie o 3:15 w nocy.
    *   **Akcja:** Tworzy galerie i linkuje zasoby (odpowiednik `bin/magento gardenlawn:mediagallery:populate-all`).

---

## Zalecany Przepływ Pracy

### Pierwsza instalacja / Pełna synchronizacja ręczna

1.  **Krok 1: Zsynchronizuj bazę danych z S3.**
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3
    ```
2.  **Krok 2: Utwórz galerie i powiązania.**
    ```sh
    bin/magento gardenlawn:mediagallery:populate-all
    ```

### Okresowe czyszczenie bazy danych

1.  **Krok 1: Sprawdź, które zasoby zostałyby usunięte.**
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3 --dry-run --with-delete
    ```
2.  **Krok 2: Jeśli wszystko się zgadza, uruchom usuwanie.**
    ```sh
    bin/magento gardenlawn:mediagallery:sync-s3 --with-delete
    ```
