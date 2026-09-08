<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Draft         = 'draft';
    case PendingReview = 'pending_review';
    case NeedDocs      = 'need_docs';
    case Approved      = 'approved';
    case Queued        = 'queued';
    case Published     = 'published';
    case Funding       = 'funding';
    case Funded        = 'funded';
    case Halted        = 'halted';
    case Closed        = 'closed';
    case Rejected      = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft         => 'پیش‌نویس',
            self::PendingReview => 'در بررسی',
            self::NeedDocs      => 'نیازمند مدرک',
            self::Approved      => 'تاییدشده',
            self::Queued        => 'در صف',
            self::Published     => 'منتشرشده',
            self::Funding       => 'در حال تامین',
            self::Funded        => 'تامین‌شده',
            self::Halted        => 'متوقف',
            self::Closed        => 'بسته',
            self::Rejected      => 'ردشده',
        };
    }

    /** رنگ برچسب — مطابق توکن‌های قالب. هیچ if رنگی در ویو نوشته نشود. */
    public function colors(): array
    {
        return match ($this) {
            self::Funded, self::Approved   => ['bg' => '#EAF7F1', 'fg' => '#12805A', 'bd' => '#C9E9DA'],
            self::Halted, self::Rejected   => ['bg' => '#FFF3F3', 'fg' => '#C43034', 'bd' => '#F5C9C9'],
            self::NeedDocs, self::Queued   => ['bg' => '#FFF8EA', 'fg' => '#8A5200', 'bd' => '#F0D49A'],
            self::Published, self::Funding => ['bg' => '#F1F6FE', 'fg' => '#144A9C', 'bd' => '#C9DDF8'],
            default                        => ['bg' => '#F5F6F8', 'fg' => '#5A6169', 'bd' => '#EDEEF1'],
        };
    }

    /** گذارهای مجاز — بخش ۴.۱ پلن. */
    public function allowed(): array
    {
        return match ($this) {
            self::Draft         => [self::PendingReview],
            self::PendingReview => [self::NeedDocs, self::Approved, self::Rejected, self::Halted],
            self::NeedDocs      => [self::Approved, self::Rejected, self::Halted],
            self::Approved      => [self::Queued, self::Halted],
            self::Queued        => [self::Published, self::Halted],
            self::Published     => [self::Funding, self::Halted, self::Closed],
            self::Funding       => [self::Funded, self::Halted],
            self::Funded        => [self::Closed],
            self::Halted        => [self::PendingReview, self::Approved, self::Queued, self::Published, self::Funding],
            self::Closed, self::Rejected => [],
        };
    }
}
