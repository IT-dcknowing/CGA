<?php

namespace App\Enums;

use App\Support\SettingDefinition;

/**
 * Paramètres de la plateforme. Les valeurs par défaut viennent des réponses du Directeur ;
 * celles marquées « à confirmer » attendent un arbitrage externe (CNCGA, SP-CGA / DGI…).
 */
enum SettingKey: string
{
    case MembershipFee = 'membership_fee';
    case UnpaidFeesAfterDays = 'unpaid_fees_after_days';
    case AttestationRequiresPaidFees = 'attestation_requires_paid_fees';
    case AttestationBlockedAfterMonths = 'attestation_blocked_after_months';
    case DeadlinesHorizonMonths = 'deadlines_horizon_months';
    case RsiRegularizationDays = 'rsi_regularization_days';
    case ApprovalExpiryAlertMonths = 'approval_expiry_alert_months';
    case CncgaAnnualFee = 'cncga_annual_fee';
    case CncgaFeeDueDate = 'cncga_fee_due_date';
    case CncgaFeeReminderDays = 'cncga_fee_reminder_days';
    case CncgaFeeProration = 'cncga_fee_proration';
    case QuarterlyReportReminderDays = 'quarterly_report_reminder_days';
    case SmsMonthlyQuota = 'sms_monthly_quota';
    case StrikeOffCancellationDays = 'strike_off_cancellation_days';
    case StruckOffAccessMonths = 'struck_off_access_months';
    case InactiveUserDisabledAfterMonths = 'inactive_user_disabled_after_months';
    case InactiveUserDeletedAfterMonths = 'inactive_user_deleted_after_months';
    case ActivityLogRetentionYears = 'activity_log_retention_years';
    case InvitationValidDays = 'invitation_valid_days';

    public function definition(): SettingDefinition
    {
        $days = ['integer', 'min:1', 'max:365'];
        $months = ['integer', 'min:1', 'max:120'];
        $amount = ['integer', 'min:0'];
        $list = ['array', 'list', 'min:1', 'max:6'];

        return match ($this) {
            self::MembershipFee => new SettingDefinition(
                label: "Droit d'adhésion par défaut (FCFA)",
                default: 25000,
                rules: $amount,
                level: SettingLevel::Cga,
                question: 'Q40',
            ),
            self::UnpaidFeesAfterDays => new SettingDefinition(
                label: 'Honoraires considérés comme impayés après (jours)',
                default: 30,
                rules: $days,
                level: SettingLevel::Cga,
                question: 'Q42',
            ),
            self::AttestationRequiresPaidFees => new SettingDefinition(
                label: 'Attestation réservée aux adhérents à jour de leurs honoraires',
                default: true,
                rules: ['boolean'],
                level: SettingLevel::Cga,
                question: 'Q77',
            ),
            self::AttestationBlockedAfterMonths => new SettingDefinition(
                label: "Attestation bloquée après (mois d'honoraires impayés)",
                default: 3,
                rules: $months,
                level: SettingLevel::Cga,
                question: 'Q42',
            ),
            self::DeadlinesHorizonMonths => new SettingDefinition(
                label: 'Échéances affichées sur (mois)',
                default: 3,
                rules: ['integer', 'min:1', 'max:12'],
                level: SettingLevel::Cga,
                question: 'Q48',
            ),
            self::RsiRegularizationDays => new SettingDefinition(
                label: "Délai de régularisation d'un adhérent passé au RSI (jours)",
                default: 90,
                rules: $days,
                level: SettingLevel::Network,
                question: 'Q31',
                toBeConfirmed: true,
            ),
            self::ApprovalExpiryAlertMonths => new SettingDefinition(
                label: "Alertes avant l'échéance de l'agrément (mois)",
                default: [6, 3],
                rules: $list,
                level: SettingLevel::Network,
                question: 'Q20',
                toBeConfirmed: true,
                itemRules: ['integer', 'min:1', 'max:24'],
            ),
            self::CncgaAnnualFee => new SettingDefinition(
                label: 'Cotisation annuelle au CNCGA (FCFA)',
                default: 750000,
                rules: $amount,
                level: SettingLevel::Network,
                question: 'Q56',
                toBeConfirmed: true,
            ),
            self::CncgaFeeDueDate => new SettingDefinition(
                label: 'Date butoir de la cotisation CNCGA (JJ/MM)',
                default: '30/06',
                rules: ['string', 'regex:/^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])$/'],
                level: SettingLevel::Network,
                question: 'Q56',
                toBeConfirmed: true,
            ),
            self::CncgaFeeReminderDays => new SettingDefinition(
                label: "Relances de cotisation (jours après l'échéance)",
                default: [30, 60, 90],
                rules: $list,
                level: SettingLevel::Network,
                question: 'Q58',
                toBeConfirmed: true,
                itemRules: $days,
            ),
            self::CncgaFeeProration => new SettingDefinition(
                label: "Prorata de cotisation d'un CGA arrivé en cours d'année (none ou semester)",
                default: 'semester',
                rules: ['string', 'in:none,semester'],
                level: SettingLevel::Network,
                question: 'Q59',
                toBeConfirmed: true,
            ),
            self::QuarterlyReportReminderDays => new SettingDefinition(
                label: 'Rappels avant la date limite du rapport trimestriel (jours)',
                default: [15, 5],
                rules: $list,
                level: SettingLevel::Network,
                question: 'Q55',
                toBeConfirmed: true,
                itemRules: $days,
            ),
            self::SmsMonthlyQuota => new SettingDefinition(
                label: 'Quota mensuel de SMS par CGA',
                default: 200,
                rules: $amount,
                level: SettingLevel::Network,
                question: 'Q69',
                toBeConfirmed: true,
            ),
            self::StrikeOffCancellationDays => new SettingDefinition(
                label: "Délai d'annulation d'une radiation (jours)",
                default: 30,
                rules: $days,
                level: SettingLevel::Network,
                question: 'Q37',
            ),
            self::StruckOffAccessMonths => new SettingDefinition(
                label: "Accès d'un adhérent radié à ses documents (mois)",
                default: 6,
                rules: $months,
                level: SettingLevel::Network,
                question: 'Q38',
            ),
            self::InactiveUserDisabledAfterMonths => new SettingDefinition(
                label: "Désactivation d'un compte inactif après (mois)",
                default: 12,
                rules: $months,
                level: SettingLevel::Network,
                question: 'Q85',
            ),
            self::InactiveUserDeletedAfterMonths => new SettingDefinition(
                label: "Suppression d'un compte inactif après (mois)",
                default: 24,
                rules: $months,
                level: SettingLevel::Network,
                question: 'Q85',
            ),
            self::ActivityLogRetentionYears => new SettingDefinition(
                label: "Conservation du journal d'audit (années)",
                default: 5,
                rules: ['integer', 'min:1', 'max:30'],
                level: SettingLevel::Network,
                question: 'Q85',
            ),
            self::InvitationValidDays => new SettingDefinition(
                label: "Validité d'un lien d'invitation (jours)",
                default: 7,
                rules: ['integer', 'min:1', 'max:30'],
                level: SettingLevel::Network,
                question: 'Q12',
                toBeConfirmed: true,
            ),
        };
    }
}
