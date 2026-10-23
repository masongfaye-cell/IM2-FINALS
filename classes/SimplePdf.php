<?php
class SimplePdf {
    private array $pages=[];
    private array $current=[];
    private float $y=0;
    private int $pageNo=0;
    private string $title;
    private string $library;
    public function __construct(string $title,string $library) { $this->title=$title; $this->library=$library; $this->newPage(); }
    private function pdfText(string $text): string {
        $converted=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
        if ($converted===false) $converted=preg_replace('/[^\x20-\x7E]/','?', $text)??'';
        return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$converted);
    }
    private function line(string $str,int $size=10,bool $bold=false,float $x=44): void {
        if ($this->y<55) $this->newPage();
        $this->current[]=sprintf('BT /%s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET', $bold?'F2':'F1',$size,$x,$this->y,$this->pdfText($str));
        $this->y-=$size+7;
    }
    private function newPage(): void {
        if ($this->current) $this->pages[]=$this->current;
        $this->pageNo++; $this->current=[]; $this->y=772;
        $this->line($this->library,12,true); $this->line($this->title.'  |  Generated '.date('Y-m-d H:i'),10);
        $this->current[]='0.75 w 44 728 m 568 728 l S'; $this->y=711;
    }
    private function wrappedLine(string $text, int $size=9, bool $bold=false, float $x=48): void {
        $printable=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
        if ($printable===false) $printable=$text;
        foreach (explode("\n", wordwrap($printable, 93, "\n", true)) as $line) {
            $this->line($line,$size,$bold,$x);
        }
    }
    public function table(array $headers,array $rows): void {
        $this->line(count($rows).' report records',10,true);
        $this->y-=7;
        if (!$rows) { $this->line('No records for the selected report.',10); return; }
        foreach ($rows as $number=>$row) {
            $needed=38;
            foreach (array_values($row) as $index=>$value) {
                $display=($headers[$index]??'Field').': '.(string)$value;
                $needed += max(1,(int)ceil(strlen($display)/93))*16;
            }
            if ($this->y - $needed < 55) $this->newPage();
            $this->line('RECORD '.str_pad((string)($number+1),4,'0',STR_PAD_LEFT),10,true);
            foreach (array_values($row) as $index=>$value) {
                $this->wrappedLine(($headers[$index]??'Field').': '.(string)$value,9,false,56);
            }
            $this->y-=9;
            if ($this->y < 60) $this->newPage();
        }
    }
    public function bytes(): string {
        if ($this->current) { $this->pages[]=$this->current; $this->current=[]; }
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>'];
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $kids=[]; $id=5;
        foreach($this->pages as $page) {
            $pageId=$id++; $contentId=$id++; $kids[]=$pageId.' 0 R';
            $stream=implode("\n",$page);
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId]='<< /Length '.strlen($stream).' >>' . "\nstream\n".$stream."\nendstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
        ksort($objects); $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach($objects as $number=>$object){ $offsets[$number]=strlen($pdf); $pdf.="$number 0 obj\n$object\nendobj\n"; }
        $xref=strlen($pdf); $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++) $pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";
        $pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
    public function download(string $filename): void {
        $contents=$this->bytes(); header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.basename($filename).'"');
        header('Content-Length: '.strlen($contents)); header('Cache-Control: no-store'); echo $contents; exit;
    }
}
