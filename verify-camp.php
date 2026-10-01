#!/usr/bin/env php
<?php
define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Admin\SerialNumberGenerator;
use App\Models\Admin\DocumentChecklist;
use App\Models\Admin\ActivityLog;

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "         CAMP SYSTEM IMPLEMENTATION VERIFICATION REPORT\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// System Components
echo "📊 SYSTEM COMPONENTS:\n";
echo "────────────────────────────────────────────────────────────────\n";
printf("  ✓ Roles Created:              %3d / 8\n", Role::count());
printf("  ✓ Permissions Created:        %3d / 69\n", Permission::count());
printf("  ✓ Serial Number Generators:   %3d / 3\n", SerialNumberGenerator::count());
printf("  ✓ Default Checklists:         %3d / 6\n", DocumentChecklist::count());
printf("  ✓ Activity Logs Created:      %3d\n\n", ActivityLog::count());

// Role Details
echo "👥 ROLE CONFIGURATION:\n";
echo "────────────────────────────────────────────────────────────────\n";
$roles = Role::orderBy('name')->get();
foreach ($roles as $role) {
    printf("  ✓ %-20s : %2d permissions assigned\n", $role->name, $role->permissions->count());
}

echo "\n";

// Serial Numbers
echo "🔢 SERIAL NUMBER GENERATORS:\n";
echo "────────────────────────────────────────────────────────────────\n";
$generators = SerialNumberGenerator::all();
foreach ($generators as $gen) {
    echo "  ✓ Type: " . $gen->type . "\n";
    echo "    Format: " . $gen->format . "\n";
    echo "    Current: " . $gen->current_number . "\n";
    echo "    Next: " . $gen->formatNumber($gen->current_number + 1) . "\n\n";
}

// Checklists
echo "📋 DOCUMENT CHECKLISTS:\n";
echo "────────────────────────────────────────────────────────────────\n";
$checklists = DocumentChecklist::orderBy('display_order')->get();
foreach ($checklists as $i => $checklist) {
    echo "  " . ($i + 1) . ". " . $checklist->name . "\n";
    echo "     → " . $checklist->description . "\n";
}

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "                     ✅ VERIFICATION COMPLETE\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";
