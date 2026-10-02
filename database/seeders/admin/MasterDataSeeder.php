<?php

namespace Database\Seeders\admin;

use App\Models\Admin\Category;
use App\Models\Admin\HigherSecondaryBoard;
use App\Models\Admin\MatriculationBoard;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\Qualification;
use App\Models\Admin\Religion;
use Illuminate\Database\Seeder;

/**
 * Default Master Data (Module 1A). Safe to re-run: only missing rows are added.
 * Rows a Super Admin deleted or deactivated stay as they are (deleted rows are not restored).
 */
class MasterDataSeeder extends Seeder
{
    protected array $religions = [
        'Buddhist', 'Christian', 'Hindu', 'Jain', 'Muslim', 'Sarnaism', 'Sikh', 'Other',
    ];

    // Added under every religion.
    protected array $categories = [
        'General',
        'Scheduled Caste & Scheduled Tribe',
        'Other Backward Classes (OBC)',
        'Economically Weaker Sections (EWS)',
        'Persons with Benchmark Disabilities',
    ];

    protected array $matriculationBoards = [
        'Central Board of Secondary Education (CBSE)',
        'Council for the Indian School Certificate Examinations (CISCE)',
        'Indian Certificate of Secondary Education (ICSE)',
        'National Institute of Open Schooling (NIOS)',
        'State Boards',
    ];

    protected array $higherSecondaryBoards = [
        'CBSE',
        'ICSE',
        'State Boards',
    ];

    // Starter list — edit on Master Data → Academic → Qualifications.
    protected array $qualifications = [
        'SSLC / 10th',
        'Plus Two / 12th (Higher Secondary)',
        'ITI',
        'Diploma',
        "Bachelor's Degree",
        "Master's Degree",
        'Other',
    ];

    // name => [code, type, sort_order, active]. Online gateways start Inactive until integrated (plan.md open question 8).
    protected array $paymentGateways = [
        'GPay (UPI)' => ['gpay', 'upi', 1, true],
        'Cash' => ['cash', 'offline', 2, true],
        'Bank Transfer (NEFT / RTGS / IMPS)' => ['bank_transfer', 'offline', 3, true],
        'Cheque / Demand Draft' => ['cheque', 'offline', 4, true],
        'Razorpay' => ['razorpay', 'online', 5, false],
        'PayU' => ['payu', 'online', 6, false],
        'PhonePe' => ['phonepe', 'online', 7, false],
    ];

    public function run()
    {
        foreach ($this->religions as $religionName) {
            $religion = Religion::withTrashed()->firstOrCreate(['name' => $religionName], ['status' => true]);

            if ($religion->trashed()) {
                continue;
            }

            foreach ($this->categories as $categoryName) {
                Category::withTrashed()->firstOrCreate(
                    ['religion_id' => $religion->id, 'name' => $categoryName],
                    ['status' => true]
                );
            }
        }

        foreach ($this->matriculationBoards as $name) {
            MatriculationBoard::withTrashed()->firstOrCreate(['name' => $name], ['status' => true]);
        }

        foreach ($this->higherSecondaryBoards as $name) {
            HigherSecondaryBoard::withTrashed()->firstOrCreate(['name' => $name], ['status' => true]);
        }

        foreach ($this->qualifications as $name) {
            Qualification::withTrashed()->firstOrCreate(['name' => $name], ['status' => true]);
        }

        foreach ($this->paymentGateways as $name => [$code, $type, $sortOrder, $active]) {
            // Matched on code: the driver key; the display name may be renamed later.
            PaymentGateway::withTrashed()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'sort_order' => $sortOrder, 'status' => $active]
            );
        }
    }
}
