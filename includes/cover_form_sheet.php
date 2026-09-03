<?php
declare(strict_types=1);

$sheetFile = $file ?? [];
$sheetUploaded = $uploaded ?? [];
$sheetGrouped = $grouped ?? cover_form_grouped(true);
$sheetCatMap = $catsByField ?? cover_form_category_map();

if (!function_exists('cover_sheet_text')) {
    function cover_sheet_text(array $field, array $file): string
    {
        return cover_form_resolve_value($file, (string) ($field['data_key'] ?? ''));
    }

    function cover_sheet_row(string $label, string $value): void
    {
        echo '<div class="f-row"><span class="f-lab">' . e($label) . ' :</span><span class="f-val">' . e($value) . '</span></div>';
    }

    function cover_sheet_check(string $label, bool $on): void
    {
        echo '<div class="chk"><span class="box' . ($on ? ' on' : '') . '" aria-hidden="true">'
            . ($on ? '✓' : '') . '</span><span>' . e($label) . '</span></div>';
    }
}
?>
<style>
    .sheet {
        width: 210mm;
        min-height: 297mm;
        margin: 16px auto;
        background: #fff;
        padding: 14mm 14mm 16mm;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .18);
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
    }
    .head, .body, .foot {
        display: grid;
        gap: 6px 28px;
    }
    .head { grid-template-columns: 1.15fr .85fr; margin-bottom: 14px; }
    .body { grid-template-columns: 1.15fr .85fr; align-items: start; }
    .foot { grid-template-columns: 1fr 1fr; margin-top: 18px; }
    .f-row { display: flex; align-items: baseline; gap: 8px; min-height: 22px; margin: 3px 0; }
    .f-lab { font-weight: 700; font-size: 12.5px; letter-spacing: .02em; white-space: nowrap; }
    .f-val { flex: 1; border-bottom: 1px solid #222; min-height: 18px; font-size: 13px; padding: 0 2px 1px; }
    .chk {
        display: flex; align-items: flex-start; gap: 8px;
        font-size: 12.5px; font-weight: 700; letter-spacing: .01em;
        margin: 5px 0; line-height: 1.25;
    }
    .box {
        width: 13px; height: 13px; border: 1.6px solid #111; flex: 0 0 13px; margin-top: 1px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 10px; line-height: 1; font-weight: 900;
    }
    .box.on { background: #111; color: #fff; border-color: #111; }
    .damage { margin-bottom: 18px; }
    .notes { grid-column: 1 / -1; margin-top: 4px; }
    .notes .f-val {
        display: block; min-height: 64px; border-bottom: none;
        border: 1px solid #222; padding: 6px 8px; white-space: pre-wrap;
    }
    @page { size: A4; margin: 10mm; }
    @media print {
        .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    }
    @media (max-width: 820px) {
        .sheet { width: auto; min-height: 0; margin: 8px; padding: 16px; }
        .head, .body, .foot { grid-template-columns: 1fr; }
    }
</style>
<main class="sheet">
    <section class="head">
        <div>
            <?php foreach ($sheetGrouped['header_left'] ?? [] as $field):
                if (($field['kind'] ?? '') !== 'text') continue;
                cover_sheet_row((string) $field['label'], cover_sheet_text($field, $sheetFile));
            endforeach; ?>
        </div>
        <div>
            <?php foreach ($sheetGrouped['header_right'] ?? [] as $field):
                if (($field['kind'] ?? '') !== 'text') continue;
                cover_sheet_row((string) $field['label'], cover_sheet_text($field, $sheetFile));
            endforeach; ?>
        </div>
    </section>
    <section class="body">
        <div>
            <?php foreach ($sheetGrouped['checks_left'] ?? [] as $field):
                if (($field['kind'] ?? '') !== 'check') continue;
                cover_sheet_check(
                    (string) $field['label'],
                    cover_form_is_checked($field, $sheetFile, $sheetUploaded, $sheetCatMap)
                );
            endforeach; ?>
        </div>
        <div>
            <div class="damage">
                <?php foreach ($sheetGrouped['damage'] ?? [] as $field):
                    if (($field['kind'] ?? '') === 'check') {
                        cover_sheet_check(
                            (string) $field['label'],
                            cover_form_is_checked($field, $sheetFile, $sheetUploaded, $sheetCatMap)
                        );
                        continue;
                    }
                    if (($field['kind'] ?? '') === 'notes') {
                        echo '<div class="notes"><div class="f-row"><span class="f-lab">' . e((string) $field['label']) . ' :</span></div>';
                        echo '<div class="f-val">' . e(cover_sheet_text($field, $sheetFile)) . '</div></div>';
                        continue;
                    }
                    cover_sheet_row((string) $field['label'], cover_sheet_text($field, $sheetFile));
                endforeach; ?>
            </div>
            <?php foreach ($sheetGrouped['checks_right'] ?? [] as $field):
                if (($field['kind'] ?? '') !== 'check') continue;
                cover_sheet_check(
                    (string) $field['label'],
                    cover_form_is_checked($field, $sheetFile, $sheetUploaded, $sheetCatMap)
                );
            endforeach; ?>
        </div>
    </section>
    <section class="foot">
        <?php foreach ($sheetGrouped['footer'] ?? [] as $field):
            $kind = $field['kind'] ?? 'text';
            if ($kind === 'check') {
                cover_sheet_check(
                    (string) $field['label'],
                    cover_form_is_checked($field, $sheetFile, $sheetUploaded, $sheetCatMap)
                );
                continue;
            }
            if ($kind === 'notes') {
                echo '<div class="notes"><div class="f-row" style="align-items:flex-start"><span class="f-lab">'
                    . e((string) $field['label']) . ' :</span></div>';
                echo '<div class="f-val">' . e(cover_sheet_text($field, $sheetFile)) . '</div></div>';
                continue;
            }
            cover_sheet_row((string) $field['label'], cover_sheet_text($field, $sheetFile));
        endforeach; ?>
    </section>
</main>
