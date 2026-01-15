<?php
require_once __DIR__ . '/fpdf.php';

class PolicyPDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial','B',16);
        $this->Cell(0,8,'Motor Insurance Policy',0,1,'L');
        $this->SetFont('Arial','',10);
        $this->SetTextColor(90,90,90);
        $this->Cell(0,6,'MedGulf Internship Demo - System Generated',0,1,'L');
        $this->SetTextColor(0,0,0);
        $this->Ln(2);
        $this->SetDrawColor(220,220,220);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(6);
    }

    function Footer()
    {
        $this->SetY(-18);
        $this->SetDrawColor(220,220,220);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
        $this->SetFont('Arial','',8);
        $this->SetTextColor(110,110,110);
        $this->Cell(0,5,'This document is system-generated for demonstration purposes and valid without signature.',0,1,'L');
        $this->Cell(0,5,'Page '.$this->PageNo(),0,0,'R');
        $this->SetTextColor(0,0,0);
    }

    function SectionTitle($txt)
    {
        $this->SetFillColor(245,246,248);
        $this->SetDrawColor(230,232,235);
        $this->SetFont('Arial','B',11);
        $this->Cell(0,8,$txt,1,1,'L',true);
        $this->Ln(2);
    }

    function KeyValueRow($k1, $v1, $k2=null, $v2=null)
    {
        $this->SetFont('Arial','B',9);
        $this->Cell(32,6,$k1,0,0,'L');
        $this->SetFont('Arial','',9);
        $this->Cell(63,6,$v1,0,0,'L');

        if($k2 !== null){
            $this->SetFont('Arial','B',9);
            $this->Cell(32,6,$k2,0,0,'L');
            $this->SetFont('Arial','',9);
            $this->Cell(0,6,$v2,0,1,'L');
        } else {
            $this->Ln(6);
        }
    }

    function TableHeader($c1, $c2)
    {
        $this->SetFillColor(20,20,20);
        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','B',9);
        $this->Cell(140,8,$c1,0,0,'L',true);
        $this->Cell(50,8,$c2,0,1,'R',true);
        $this->SetTextColor(0,0,0);
    }

    function TableRow($left, $right)
    {
        $this->SetFont('Arial','',9);
        $this->SetDrawColor(230,232,235);
        $this->Cell(140,7,$left,0,0,'L');
        $this->Cell(50,7,$right,0,1,'R');
    }

    function Money($n)
    {
        return '$' . number_format((float)$n, 2);
    }
}
