<?php

namespace ClinicalLab\Infrastructure\Pdf;

use ClinicalLab\Domain\Entity\Aliado;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\Patient;
use Dompdf\Dompdf;
use Dompdf\Options;

class ResultPdfGenerator
{
    private const PDF_DIR = __DIR__ . '/../../../../storage/pdfs/';

    public function generate(
        LabOrder $order,
        Patient  $patient,
        ?Aliado  $aliado,
        array    $resultados
    ): string {        // Aumentar límites para generación de PDF
        ini_set('memory_limit', '256M');
        set_time_limit(120);

        $html = $this->buildHtml($order, $patient, $aliado, $resultados);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);   // false evita llamadas externas que pueden colgar
        $options->set('defaultFont', 'Helvetica'); // fuente más liviana que DejaVu Sans
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if (!is_dir(self::PDF_DIR)) {
            mkdir(self::PDF_DIR, 0755, true);
        }

        $filename = 'resultado_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $order->getIdSolicitudKey()) . '.pdf';
        file_put_contents(self::PDF_DIR . $filename, $dompdf->output());

        return '/storage/pdfs/' . $filename;
    }

    public function getFullPath(string $relativePath): string
    {
        return __DIR__ . '/../../../../' . ltrim($relativePath, '/');
    }

    /** Ruta del logo institucional del hospital (relativa a la raíz del proyecto). */
    private const HOSPITAL_LOGO_PATH = 'storage/logos/hospital_materno_infantil.png';

    private function buildHtml(LabOrder $order, Patient $patient, ?Aliado $aliado, array $resultados): string
    {
        // ── Logo institucional (izquierda) ────────────────────────────────────
        $hospitalLogoHtml = '';
        $hospitalLogoFull = __DIR__ . '/../../../../' . self::HOSPITAL_LOGO_PATH;
        if (file_exists($hospitalLogoFull)) {
            $data             = base64_encode(file_get_contents($hospitalLogoFull));
            $mime             = str_ends_with($hospitalLogoFull, '.png') ? 'image/png' : 'image/jpeg';
            $hospitalLogoHtml = "<img src=\"data:{$mime};base64,{$data}\" style=\"max-height:70px;\">";
        }

        // ── Logo del aliado (derecha) ─────────────────────────────────────────
        $aliadoLogoHtml = '';
        if ($aliado?->getLogoPath()) {
            $logoFullPath = __DIR__ . '/../../../../' . ltrim($aliado->getLogoPath(), '/');
            if (file_exists($logoFullPath)) {
                $logoData       = base64_encode(file_get_contents($logoFullPath));
                $mime           = str_ends_with($logoFullPath, '.png') ? 'image/png' : 'image/jpeg';
                $aliadoLogoHtml = "<img src=\"data:{$mime};base64,{$logoData}\" style=\"max-height:70px;\">";
            }
        }

        $aliadoNombre = htmlspecialchars($aliado?->getNombre() ?? $order->getIdAliado() ?? '');
        $aliadoNit    = htmlspecialchars($aliado?->getNit()    ?? '');
        $aliadoDir    = htmlspecialchars($aliado?->getDireccion() ?? '');
        $aliadoEmail  = htmlspecialchars($aliado?->getEmail()  ?? '');
        $sexoLabel    = $patient->getSexo() === 'M' ? 'Masculino' : 'Femenino';
        $edad         = $patient->getFechaNacimiento()->diff(new \DateTimeImmutable())->y;
        $resultadosHtml = $this->buildResultadosHtml($resultados);
        $bacteriologoHtml = $this->buildBacteriologoHtml($resultados);
        $generadoEn   = (new \DateTimeImmutable())->format('d/m/Y H:i:s');
        $nombrePac    = htmlspecialchars($patient->getNombre());
        $docPac       = htmlspecialchars($patient->getTipoDocumento() . ' ' . $patient->getIdentificacion());
        $fechaNac     = $patient->getFechaNacimiento()->format('d/m/Y');
        $idSol        = htmlspecialchars($order->getIdSolicitudKey());
        $fechaOrden   = $order->getFechaDeLaOrden()->format('d/m/Y H:i');
        $medico       = htmlspecialchars($order->getMedicoQueOrdena());
        $centro       = htmlspecialchars($order->getCentroDeSalud());

        return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
<style>
body{font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#222;margin:20px}
h1{font-size:15px;color:#1a5276;margin:0}
.subtitle{font-size:10px;color:#555;margin-top:3px}
.title-report{font-size:12px;font-weight:bold;color:#1a5276;margin-top:4px}
hr.top{border:2px solid #1a5276;margin:10px 0 14px 0}
.section-title{background:#1a5276;color:#fff;padding:4px 8px;font-size:11px;font-weight:bold;margin:10px 0 6px 0}
table.info{width:100%;border-collapse:collapse;margin-bottom:8px}
table.info td{padding:2px 6px;font-size:11px}
table.info td.label{width:140px;font-weight:bold;color:#444}
table.results{width:100%;border-collapse:collapse;font-size:10px;margin-bottom:12px}
table.results th{background:#d6eaf8;padding:4px 6px;text-align:left;border:1px solid #aed6f1}
table.results td{padding:3px 6px;border:1px solid #d5d8dc}
.exam-title{font-size:12px;color:#1a5276;border-bottom:1px solid #aed6f1;padding-bottom:3px;margin:10px 0 6px 0;font-weight:bold}
.flag{padding:2px 5px;font-size:9px;font-weight:bold}
.fn{background:#d5f5e3;color:#1e8449}
.fw{background:#fef9e7;color:#b7950b}
.fc{background:#fadbd8;color:#c0392b}
.fx{background:#eaecee;color:#555}
.footer{border-top:1px solid #ccc;margin-top:20px;padding-top:6px;font-size:9px;color:#888;text-align:center}
</style></head><body>
<table width='100%'><tr>
<td width='120' style='vertical-align:middle'>{$hospitalLogoHtml}</td>
<td style='padding-left:14px;vertical-align:middle'>
<h1>{$aliadoNombre}</h1>
<div class='subtitle'>NIT: {$aliadoNit} | {$aliadoDir} | {$aliadoEmail}</div>
<div class='title-report'>INFORME DE RESULTADOS DE LABORATORIO</div>
</td>
<td width='120' style='text-align:right;vertical-align:middle'>{$aliadoLogoHtml}</td>
</tr></table>
<hr class='top'>
<div class='section-title'>DATOS DEL PACIENTE</div>
<table class='info'>
<tr><td class='label'>Nombre:</td><td>{$nombrePac}</td></tr>
<tr><td class='label'>Documento:</td><td>{$docPac}</td></tr>
<tr><td class='label'>Sexo / Edad:</td><td>{$sexoLabel} / {$edad} años</td></tr>
<tr><td class='label'>Fecha nacimiento:</td><td>{$fechaNac}</td></tr>
</table>
<div class='section-title'>DATOS DE LA ORDEN</div>
<table class='info'>
<tr><td class='label'>N° Solicitud:</td><td>{$idSol}</td></tr>
<tr><td class='label'>Fecha orden:</td><td>{$fechaOrden}</td></tr>
<tr><td class='label'>Médico:</td><td>{$medico}</td></tr>
<tr><td class='label'>Centro de salud:</td><td>{$centro}</td></tr>
</table>
<div class='section-title'>RESULTADOS</div>
{$resultadosHtml}
{$bacteriologoHtml}
<div class='footer'>Documento generado el {$generadoEn} | Este informe es de uso exclusivo médico.</div>
</body></html>";
    }

    private function buildResultadosHtml(array $resultados): string
    {
        $html = '';
        foreach ($resultados as $res) {
            $cups  = htmlspecialchars($res['cups']);
            $html .= "<div class='exam-title'>{$cups}</div>
            <table class='results'><thead><tr>
                <th>Parámetro</th><th>Resultado</th><th>Unidad</th><th>Referencia</th><th>Interpretación</th>
            </tr></thead><tbody>";

            if (!empty($res['valoresEstructurados'])) {
                foreach ($res['valoresEstructurados'] as $v) {
                    $valor = $v['valorNumerico']
                          ?? $v['valorTexto']
                          ?? ($v['valorBooleano'] !== null ? ($v['valorBooleano'] ? 'Positivo' : 'Negativo') : '—');
                    $ref   = ($v['valorMinRef'] !== null || $v['valorMaxRef'] !== null)
                           ? (($v['valorMinRef'] ?? '') . ' - ' . ($v['valorMaxRef'] ?? '')) : '—';
                    $flag  = $v['flag'] ?? 'indeterminado';
                    $fc    = match($flag) {
                        'normal'              => 'fn',
                        'alto','bajo','positivo','reactivo' => 'fw',
                        'critico'             => 'fc',
                        default               => 'fx',
                    };
                    $fl    = match($flag) {
                        'normal'      => 'Normal',
                        'alto'        => 'Alto',
                        'bajo'        => 'Bajo',
                        'critico'     => 'CRITICO',
                        'positivo'    => 'Positivo',
                        'negativo'    => 'Negativo',
                        'reactivo'    => 'Reactivo',
                        'no_reactivo' => 'No Reactivo',
                        default       => '—',
                    };
                    $nom   = htmlspecialchars($v['nombre'] ?? $v['codigo'] ?? '');
                    $uni   = htmlspecialchars($v['unidad'] ?? '');
                    $com   = !empty($v['comentario'])
                           ? '<br><span style="font-size:8px;color:#555;font-style:italic">'
                             . htmlspecialchars($v['comentario']) . '</span>'
                           : '';
                    $html .= "<tr>
                        <td>{$nom}{$com}</td>
                        <td><strong>" . htmlspecialchars((string) $valor) . "</strong></td>
                        <td>{$uni}</td>
                        <td>{$ref}</td>
                        <td><span class='flag {$fc}'>{$fl}</span></td>
                    </tr>";
                }
            } else {
                foreach (($res['valuesJson'] ?? []) as $k => $v) {
                    if (is_array($v)) {
                        $display = htmlspecialchars((string) ($v['valor'] ?? $v['value'] ?? json_encode($v)));
                        $unidad  = htmlspecialchars((string) ($v['unidad'] ?? $v['unit'] ?? ''));
                        $ref     = htmlspecialchars((string) ($v['referencia'] ?? $v['reference'] ?? ''));
                        $html   .= "<tr>
                            <td>" . htmlspecialchars($k) . "</td>
                            <td><strong>{$display}</strong></td>
                            <td>{$unidad}</td>
                            <td>{$ref}</td>
                            <td>—</td>
                        </tr>";
                    } else {
                        $html .= "<tr>
                            <td>" . htmlspecialchars($k) . "</td>
                            <td colspan='4'>" . htmlspecialchars((string) $v) . "</td>
                        </tr>";
                    }
                }
            }
            $html .= "</tbody></table>
            <p style='font-size:9px;color:#888;text-align:right;margin-bottom:8px'>Recibido: "
                   . htmlspecialchars($res['receivedAt'] ?? '') . "</p>";

            // Antibiogramas
            if (!empty($res['antibiogramas'])) {
                foreach ($res['antibiogramas'] as $ab) {
                    $bacteria = htmlspecialchars($ab['bacteriaAislada'] ?? '');
                    $gram     = $ab['gram'] ? ' | Gram: ' . htmlspecialchars(ucfirst($ab['gram'])) : '';
                    $incub    = $ab['tiempoIncubacion'] ? ' | Incubación: ' . htmlspecialchars($ab['tiempoIncubacion']) : '';
                    $gramOrina = !empty($ab['gramOrina'])
                        ? '<p style="font-size:10px;margin:4px 0"><strong>Gram directo:</strong> ' . htmlspecialchars($ab['gramOrina']) . '</p>'
                        : '';
                    $obs = !empty($ab['observaciones'])
                        ? '<p style="font-size:10px;margin:4px 0"><strong>Observaciones:</strong> ' . htmlspecialchars($ab['observaciones']) . '</p>'
                        : '';

                    $html .= "<div style='margin:8px 0;padding:8px;background:#f9f9f9;border-left:3px solid #1a5276'>"
                           . "<p style='font-size:11px;font-weight:bold;margin:0 0 4px 0'>Bacteria aislada: {$bacteria}{$gram}{$incub}</p>"
                           . $gramOrina . $obs;

                    if (!empty($ab['items'])) {
                        $html .= "<table class='results' style='margin-top:6px'><thead><tr>"
                               . "<th>Antibiótico</th><th>CIM</th><th>Sensibilidad</th><th>Método</th>"
                               . "</tr></thead><tbody>";
                        foreach ($ab['items'] as $item) {
                            $sens  = $item['sensibilidad'] ?? '—';
                            $color = match($sens) {
                                'S'     => '#d5f5e3',
                                'I'     => '#fef9e7',
                                'R'     => '#fadbd8',
                                default => '#f4f4f4',
                            };
                            $label = match($sens) {
                                'S'     => 'Sensible',
                                'I'     => 'Intermedio',
                                'R'     => 'Resistente',
                                default => '—',
                            };
                            $html .= "<tr>"
                                   . "<td>" . htmlspecialchars($item['antibiotico'] ?? '') . "</td>"
                                   . "<td>" . htmlspecialchars($item['cim'] ?? '—') . "</td>"
                                   . "<td style='background:{$color};font-weight:bold'>{$label}</td>"
                                   . "<td style='font-size:9px'>" . htmlspecialchars($item['metodo'] ?? '—') . "</td>"
                                   . "</tr>";
                        }
                        $html .= "</tbody></table>";
                    }
                    $html .= "</div>";
                }
            }
        }
        return $html;
    }

    private function buildBacteriologoHtml(array $resultados): string
    {
        $bacteriologos = [];
        foreach ($resultados as $res) {
            $b = $res['bacteriologo'] ?? null;
            if ($b && !isset($bacteriologos[$b['id']])) {
                $bacteriologos[$b['id']] = $b;
            }
        }

        if (empty($bacteriologos)) {
            return '';
        }

        $html = "<div style='margin-top:20px;border-top:1px solid #ccc;padding-top:10px'>";

        foreach ($bacteriologos as $b) {
            $firmaHtml = '';
            if (!empty($b['firmaPath'])) {
                $firmaFullPath = __DIR__ . '/../../../../' . ltrim($b['firmaPath'], '/');
                if (file_exists($firmaFullPath)) {
                    $firmaData = base64_encode(file_get_contents($firmaFullPath));
                    $mime      = str_ends_with($firmaFullPath, '.png') ? 'image/png' : 'image/jpeg';
                    $firmaHtml = "<br><img src='data:{$mime};base64,{$firmaData}' style='max-height:50px;max-width:150px;'>";
                }
            }

            $nombre  = htmlspecialchars($b['nombre'] ?? '');
            $tarjeta = htmlspecialchars($b['tarjetaProfesional'] ?? '');
            $univ    = htmlspecialchars($b['universidad'] ?? '');
            $doc     = htmlspecialchars(($b['tipoDocumento'] ?? '') . ' ' . ($b['identificacion'] ?? ''));

            $html .= "<table width='200' style='display:inline-table;margin-right:30px;vertical-align:top;font-size:10px;text-align:center'>"
                   . "<tr><td>{$firmaHtml}</td></tr>"
                   . "<tr><td style='border-top:1px solid #333;padding-top:4px'><strong>{$nombre}</strong></td></tr>"
                   . "<tr><td>Bacteriólogo</td></tr>";

            if ($tarjeta) {
                $html .= "<tr><td>T.P. {$tarjeta}</td></tr>";
            }
            if ($univ) {
                $html .= "<tr><td style='font-size:9px;color:#555'>{$univ}</td></tr>";
            }
            $html .= "<tr><td style='font-size:9px;color:#555'>{$doc}</td></tr>"
                   . "</table>";
        }

        $html .= "</div>";
        return $html;
    }
}
