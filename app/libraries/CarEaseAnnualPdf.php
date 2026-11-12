<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Small dependency-free PDF writer for CarEase annual reports.
 * Uses PDF standard fonts and a local JPEG logo. Currency is rendered as PHP.
 */
class CarEaseAnnualPdf
{
    private $pages = [];
    private $ops = '';
    private $images = [];
    private $w = 595.28;
    private $h = 841.89;
    private $pageNo = 0;

    private function color($hex) {
        $hex = ltrim($hex, '#');
        return implode(' ', array_map(static function ($n) use ($hex) {
            return number_format(hexdec(substr($hex, $n, 2)) / 255, 3, '.', '');
        }, [0, 2, 4]));
    }
    private function esc($s) {
        $s = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$s);
        return str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)',' ',' '], $s);
    }
    public function rect($x,$top,$w,$h,$hex) {
        $this->ops .= $this->color($hex)." rg $x ".($this->h-$top-$h)." $w $h re f\n";
    }
    public function text($x,$top,$str,$size=10,$hex='#182D45',$bold=false) {
        $font=$bold?'F2':'F1';
        $this->ops .= $this->color($hex)." rg BT /$font $size Tf 1 0 0 1 $x ".($this->h-$top-$size)." Tm (".$this->esc($str).") Tj ET\n";
    }
    public function line($x,$top,$x2,$top2,$hex='#E1E8F0') {
        $this->ops .= $this->color($hex)." RG 0.7 w $x ".($this->h-$top)." m $x2 ".($this->h-$top2)." l S\n";
    }
    public function image($file,$x,$top,$width,$height) {
        if (!is_file($file)) return;
        $key = 'Im1';
        $this->images[$key] = $file;
        $this->ops .= "q $width 0 0 $height $x ".($this->h-$top-$height)." cm /$key Do Q\n";
    }
    public function page($title='') {
        if ($this->pageNo) $this->pages[]=$this->ops;
        $this->pageNo++;
        $this->ops='';
        $this->rect(0,0,$this->w,78,'#091A30');
        $this->rect(0,78,$this->w,3,'#1599E8');
        $this->text(42,20,'CAREASE  /  ANNUAL REPORT',15,'#FFFFFF',true);
        $this->text(42,47,$title,9,'#BED0E3');
        $this->line(42,801,553,801,'#D7A943');
        $this->text(42,811,'CAREASE RENTALS  |  DYNAMIC DATABASE REPORT',8,'#64748B');
        $this->text(501,811,'PAGE '.$this->pageNo,8,'#64748B');
    }
    private function object($data,&$objects) {
        $objects[]=$data; return count($objects);
    }
    public function output($filename) {
        if ($this->pageNo) $this->pages[]=$this->ops;
        $objects=[];
        $catalog=$this->object('', $objects);
        $pagesRoot=$this->object('', $objects);
        $f1=$this->object('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',$objects);
        $f2=$this->object('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',$objects);
        $xobjects='';
        foreach($this->images as $name=>$file) {
            $raw=file_get_contents($file);
            $info=getimagesize($file);
            $img=$this->object('<< /Type /XObject /Subtype /Image /Width '.$info[0].' /Height '.$info[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($raw)." >>\nstream\n".$raw."\nendstream",$objects);
            $xobjects.="/$name $img 0 R ";
        }
        $resources='<< /Font << /F1 '.$f1.' 0 R /F2 '.$f2.' 0 R >> /XObject << '.$xobjects.'>> >>';
        $kids=[];
        foreach($this->pages as $ops) {
            $stream=$this->object('<< /Length '.strlen($ops)." >>\nstream\n".$ops."endstream",$objects);
            $page=$this->object('<< /Type /Page /Parent '.$pagesRoot.' 0 R /MediaBox [0 0 595.28 841.89] /Resources '.$resources.' /Contents '.$stream.' 0 R >>',$objects);
            $kids[]=$page.' 0 R';
        }
        $objects[$pagesRoot-1]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
        $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pagesRoot.' 0 R >>';
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $i=>$obj) {$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
        $pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root $catalog 0 R >>\nstartxref\n$xref\n%%EOF";
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$filename.'"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
    }
}
