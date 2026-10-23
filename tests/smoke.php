<?php
require __DIR__.'/../app/functions.php';
require __DIR__.'/../classes/SimplePdf.php';
function check(bool $yes, string $label): void { if (!$yes) { fwrite(STDERR,"FAIL $label\n");exit(1); } echo "PASS $label\n"; }
check(e('<script>')==='&lt;script&gt;','HTML escaping');
check(safe_date('2026-02-29')===null && safe_date('2028-02-29')==='2028-02-29','strict date validation');
check(str_starts_with(csv_cell('=SUM(1,1)'),"'"),'CSV injection escaping');
check(validate_email('reader@example.org') && !validate_email('not an email'),'email validation');
$doc=new SimplePdf('Smoke test','Oakridge Community Library');
$doc->table(['Book','Patron'],[['Title A','Reader A'],['Title B','Reader B']]);
$bytes=$doc->bytes();
check(str_starts_with($bytes,'%PDF-1.4') && str_contains($bytes,'%%EOF'),'PDF export');
echo "All smoke checks passed.\n";
