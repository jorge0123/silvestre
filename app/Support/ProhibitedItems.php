<?php

namespace App\Support;

use App\Enums\RiskLevel;

/**
 * La política de artículos prohibidos, como datos.
 *
 * Vive aquí y no repartida en ifs porque tiene que poder auditarse, citarse en
 * los Términos de uso y cambiarse sin tocar lógica. Contexto: Guatemala.
 *
 * ADVERTENCIA HONESTA: esta lista es una primera barrera, no un cumplimiento
 * legal. Un abogado guatemalteco tiene que revisar los Términos antes de abrir
 * al público, y varias categorías (alimentos, salud, alcohol, servicios
 * financieros) están reguladas por MSPAS, los colegios profesionales o la SIB
 * con requisitos concretos que cambian.
 */
class ProhibitedItems
{
    /**
     * Inequívocos: se bloquea la publicación. Solo entran términos que no
     * tienen un uso legítimo plausible en un catálogo.
     *
     * @return array<string, array<int, string>>
     */
    public static function prohibited(): array
    {
        return [
            'Drogas' => [
                'cocaina', 'cocaína', 'metanfetamina', 'cristal meth', 'heroina', 'heroína',
                'fentanilo', 'lsd', 'mdma', 'extasis', 'éxtasis', 'crack', 'peyote',
            ],
            'Medicamentos controlados' => [
                'clonazepam', 'alprazolam', 'tramadol', 'oxicodona', 'ritalin',
                'adderall', 'misoprostol', 'cytotec', 'sin receta medica', 'sin receta médica',
            ],
            'Armas de fuego y explosivos' => [
                'arma de fuego', 'municion', 'munición', 'cartuchos calibre', 'silenciador',
                'granada', 'explosivo', 'polvora', 'pólvora', 'ar-15', 'ak-47',
            ],
            'Documentos falsos' => [
                'dpi falso', 'pasaporte falso', 'licencia falsa', 'nit falso',
                'titulo falso', 'título falso', 'antecedentes penales falsos',
                'factura falsa', 'fel falsa', 'comprobante falso', 'sello apocrifo', 'sello apócrifo',
            ],
            'Datos y medios de pago robados' => [
                'tarjeta clonada', 'tarjetas clonadas', 'dumps cvv', 'cuenta bancaria vendida',
                'datos bancarios', 'base de datos de clientes',
            ],
            'Personas y órganos' => [
                'venta de riñon', 'venta de riñón', 'venta de organo', 'venta de órgano',
                'adopcion de bebe', 'adopción de bebé', 'vientre en renta',
            ],
            // Fauna protegida por CONAP. Términos específicos a propósito:
            // "quetzal" solo bloquearía la moneda ("cuesta un quetzal") y
            // "jaguar" una playera estampada.
            'Especies protegidas' => [
                'ave quetzal', 'plumas de quetzal', 'cachorro de jaguar', 'piel de jaguar',
                'guacamaya roja', 'pavo ocelado', 'manati', 'manatí', 'tortuga parlama',
                'huevos de parlama', 'mono aullador', 'tucan vivo', 'tucán vivo', 'marfil',
            ],
            'Servicios sexuales' => [
                'sexoservicio', 'escort', 'servicios sexuales', 'acompañante sexual',
            ],
            'Dinero falso' => [
                'billetes falsos', 'dinero falso', 'moneda falsificada',
            ],
        ];
    }

    /**
     * Legales pero regulados: se publica solo si el negocio subió el permiso
     * que corresponde. Aquí es donde vive el cumplimiento real.
     *
     * @return array<string, array{terms: array<int, string>, document: string}>
     */
    public static function restricted(): array
    {
        return [
            'Bebidas alcohólicas' => [
                'terms' => ['cerveza', 'tequila', 'quetzalteca', 'vino', 'whisky', 'ron', 'vodka', 'licor', 'cusha'],
                'document' => 'Licencia sanitaria y patente de comercio',
            ],
            'Tabaco y vapeo' => [
                'terms' => ['cigarro', 'tabaco', 'vape', 'vapeador', 'nicotina', 'puro'],
                'document' => 'Licencia sanitaria (MSPAS)',
            ],
            'Alimentos preparados' => [
                'terms' => ['comida preparada', 'lacteos', 'lácteos', 'carne cruda',
                    'mariscos', 'ceviche', 'embutidos', 'pasteleria', 'pastelería'],
                'document' => 'Licencia sanitaria (MSPAS)',
            ],
            'Suplementos y remedios' => [
                'terms' => ['suplemento', 'proteina', 'proteína', 'vitamina inyectable',
                    'producto milagro', 'quema grasa', 'reductivo'],
                'document' => 'Registro sanitario (MSPAS)',
            ],
            'Estética invasiva' => [
                'terms' => ['botox', 'acido hialuronico', 'ácido hialurónico', 'relleno facial',
                    'lipolitico', 'lipolítico', 'mesoterapia', 'plasma rico'],
                'document' => 'Colegiado activo y licencia sanitaria',
            ],
            'Servicios de salud' => [
                'terms' => ['consulta medica', 'consulta médica', 'dentista', 'odontologia',
                    'odontología', 'psicologo', 'psicólogo', 'nutriologo', 'nutriólogo',
                    'enfermeria', 'enfermería', 'fisioterapia'],
                'document' => 'Constancia de colegiado activo',
            ],
            'Servicios profesionales colegiados' => [
                'terms' => ['abogado', 'notario', 'contador publico', 'contador público',
                    'arquitecto', 'ingeniero civil', 'auditor'],
                'document' => 'Constancia de colegiado activo',
            ],
            'Servicios financieros' => [
                'terms' => ['prestamo', 'préstamo', 'credito personal', 'crédito personal',
                    'casa de empeño', 'inversion garantizada', 'inversión garantizada',
                    'criptomoneda', 'forex'],
                'document' => 'Autorización de la Superintendencia de Bancos (SIB)',
            ],
            'Animales' => [
                'terms' => ['cachorro', 'perro en venta', 'gato en venta', 'criadero'],
                'document' => 'Constancia de médico veterinario colegiado',
            ],
            'Armas blancas y herramientas' => [
                'terms' => ['machete', 'navaja mariposa', 'manopla', 'taser', 'gas pimienta'],
                'document' => 'Comprobante de uso legítimo',
            ],
        ];
    }

    /**
     * Señales ambiguas: nunca bloquean, solo mandan a revisión humana.
     * Aquí van las promesas engañosas y los indicios de mercancía robada.
     *
     * @return array<string, array<int, string>>
     */
    public static function sensitive(): array
    {
        return [
            'Promesas médicas' => [
                'cura el cancer', 'cura el cáncer', 'cura la diabetes', 'elimina tumores',
                'sana cualquier', 'resultados garantizados', 'baja 10 kilos',
                'sin dieta ni ejercicio', 'aprobado por la fda',
            ],
            'Posible mercancía robada' => [
                'sin factura', 'sin papeles', 'liberado de fabrica', 'liberado de fábrica',
                'no preguntes', 'de procedencia',
            ],
            'Posible falsificación' => [
                'replica aaa', 'réplica aaa', 'calidad espejo', 'clon', 'imitacion original',
                'imitación original', 'triple a',
            ],
            'Promesas de rendimiento' => [
                'ganancia asegurada', 'duplica tu dinero', 'sin riesgo', 'retorno garantizado',
            ],
            'Armas ambiguas' => [
                'pistola', 'rifle', 'cuchillo', 'arma',
            ],
        ];
    }

    /** Versión de la política. Cambiarla obliga a reaceptar los Términos. */
    public const POLICY_VERSION = '2026.09-gt';

    /**
     * Resumen legible de lo prohibido, para mostrarlo en el onboarding antes
     * de aceptar las reglas.
     *
     * @return array<int, string>
     */
    public static function prohibitedSummary(): array
    {
        return array_keys(self::prohibited());
    }
}
