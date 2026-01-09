<?php
class SimplePDF {

    private $lines = [];
    private $font = 'Helvetica';

    public function addLine($x, $y, $size, $text){
        $this->lines[] = [
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'text' => $text
        ];
    }

    public function output($filename = 'document.pdf'){
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$filename.'"');

        echo "%PDF-1.4\n";
        echo "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        echo "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        echo "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        $content = "BT\n/F1 12 Tf\n";
        foreach($this->lines as $line){
            $content .= sprintf(
                "1 0 0 1 %d %d Tm\n(%s) Tj\n",
                $line['x'],
                $line['y'],
                $this->escape($line['text'])
            );
        }
        $content .= "ET\n";

        echo "4 0 obj\n<< /Length ".strlen($content)." >>\nstream\n";
        echo $content;
        echo "endstream\nendobj\n";

        echo "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /".$this->font." >>\nendobj\n";
        echo "xref\n0 6\n0000000000 65535 f \n";
        echo "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n";
        echo "%%EOF";
        exit;
    }

    private function escape($text){
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
