<?php

namespace App\Enums;

/**
 * Motivos tipificados de reporte.
 *
 * Tipificarlos no es burocracia: permite priorizar la cola (un reporte por
 * producto ilegal se atiende en horas; uno por "no me gustó", nunca) y
 * medir qué se rompe más seguido.
 */
enum ReportReason: string
{
    case IllegalItem = 'illegal_item';
    case RegulatedNoPermit = 'regulated_no_permit';
    case Counterfeit = 'counterfeit';
    case StolenGoods = 'stolen_goods';
    case UnsafeFood = 'unsafe_food';
    case MedicalClaims = 'medical_claims';
    case SexualContent = 'sexual_content';
    case Violence = 'violence';
    case Harassment = 'harassment';
    case Impersonation = 'impersonation';
    case Scam = 'scam';
    case FakeReviews = 'fake_reviews';
    case NotDelivered = 'not_delivered';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IllegalItem => 'Vende algo ilegal',
            self::RegulatedNoPermit => 'Producto regulado sin permiso',
            self::Counterfeit => 'Producto falsificado',
            self::StolenGoods => 'Parece robado',
            self::UnsafeFood => 'Alimento en mal estado o inseguro',
            self::MedicalClaims => 'Promete curar o adelgazar',
            self::SexualContent => 'Contenido sexual',
            self::Violence => 'Violencia o amenazas',
            self::Harassment => 'Acoso o discriminación',
            self::Impersonation => 'Se hace pasar por otro negocio',
            self::Scam => 'Fraude',
            self::FakeReviews => 'Reseñas falsas',
            self::NotDelivered => 'No entregó el pedido',
            self::Other => 'Otro motivo',
        };
    }

    /**
     * Horas objetivo de atención. Lo que puede lastimar a alguien se ve hoy;
     * lo comercial puede esperar. Sin este orden la cola se vuelve inútil.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::IllegalItem, self::SexualContent, self::Violence => 4,
            self::UnsafeFood, self::MedicalClaims, self::Harassment => 12,
            self::RegulatedNoPermit, self::Counterfeit,
            self::StolenGoods, self::Impersonation, self::Scam => 24,
            self::FakeReviews, self::NotDelivered => 72,
            self::Other => 96,
        };
    }

    /** Reportes que ocultan el contenido de inmediato, antes de revisarlo. */
    public function autoHides(): bool
    {
        return in_array($this, [self::IllegalItem, self::SexualContent, self::Violence], true);
    }
}
