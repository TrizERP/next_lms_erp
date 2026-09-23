<?php
use App\Domain\AI\Modules\ModuleReadTools;
$tools = new ModuleReadTools();
$selected = $tools->select(['fees.getPending', 'fees.arrears', 'fees.collection_report', 'ai.templates.list', 'ai.templates.render', 'ai.templates.generate', 'students.search', 'students.directory', 'academics.structure'], 2);
echo 'Selected: ' . implode(', ', $selected) . "\n";