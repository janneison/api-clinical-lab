<?php

namespace ClinicalLab\Application\Dto;

/**
 * DTO para registrar un antibiograma junto con un resultado de laboratorio.
 *
 * Estructura esperada en el body de POST /results:
 * {
 *   "idSolicitudKey": "SOL-2025-0001",
 *   "cups": "901236",
 *   "values": { "resultado": "Ver antibiograma" },
 *   "antibiogramas": [
 *     {
 *       "bacteriaAislada": "Escherichia coli",
 *       "gram": "negativo",
 *       "tiempoIncubacion": "48 horas",
 *       "gramOrina": "No se observan microorganismos",
 *       "observaciones": null,
 *       "items": [
 *         { "antibiotico": "Ampicilina",      "cim": ">16",  "sensibilidad": "R", "metodo": "MIC automatico" },
 *         { "antibiotico": "Ciprofloxacina",  "cim": "≤0.25","sensibilidad": "S", "metodo": "MIC automatico" },
 *         { "antibiotico": "Nitrofurantoina", "cim": "≤16",  "sensibilidad": "S", "metodo": "MIC automatico" }
 *       ]
 *     }
 *   ]
 * }
 *
 * Para cultivos negativos:
 * {
 *   "bacteriaAislada": "Negativo en 48 horas de incubación",
 *   "gram": "n/a",
 *   "tiempoIncubacion": "48 horas",
 *   "gramOrina": "No se observan microorganismos",
 *   "items": []
 * }
 */
class AntibiogramaDto
{
    /**
     * @param AntibiogramaItemDto[] $items
     */
    public function __construct(
        public readonly string  $bacteriaAislada,
        public readonly ?string $gram             = null,
        public readonly ?string $tiempoIncubacion = null,
        public readonly ?string $gramOrina        = null,
        public readonly ?string $observaciones    = null,
        public readonly array   $items            = [],
    ) {
    }
}
