<?php

namespace App\Services\lms\Prayogshala;

/**
 * Validates a Prayogshala `lab_config` before it is stored.
 *
 * A lab_config is DATA that the frontend feeds to a fixed set of trusted simulation engines
 * (lms_k12/lib/prayogshala/engines.ts). Model-written text never becomes code: formulas and
 * conditions are written in a tiny expression language (numbers, identifiers, + - * /, comparisons,
 * && ||, parentheses, !) that the frontend parses itself, and this class checks every one of them
 * here too - characters, length, and that each identifier is a fact the engine really produces -
 * so a config that could not run is refused at write time rather than found by a student.
 *
 * Mirrored on the client by lms_k12/lib/prayogshala/validate.ts, which additionally evaluates the
 * expressions (e.g. that at least one Predict option is right), something only the client's engines
 * can do. Keep the two in step: the engine list and parameter limits below are the contract.
 */
class SimulationConfigValidator
{
    public const ENGINES = ['calculator', 'relevance', 'osmosis', 'variable_model', 'sequence', 'classify'];

    /** Visual primitives variable_model may draw. Each is a trusted frontend component. */
    public const VISUALS = ['heating', 'particles', 'ray', 'circuit', 'bars', 'rectangle', 'motion', 'wave', 'lever', 'atom', 'scenery', 'mixture', 'pendulum'];

    private const STEP_KEYS = ['mission', 'predict', 'do', 'observe', 'explain', 'concept', 'apply', 'reflect'];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param  mixed  $lab
     * @return list<string> human-readable problems; empty means valid
     */
    public function validate($lab): array
    {
        $this->errors = [];

        if (! is_array($lab)) {
            return ['lab_config must be an object.'];
        }

        $sim = $lab['simulation'] ?? null;
        if (! is_array($sim) || ! isset($sim['type']) || ! is_string($sim['type'])) {
            return ['lab_config.simulation.type is required.'];
        }
        if (! in_array($sim['type'], self::ENGINES, true)) {
            return ["Unknown simulation type '{$sim['type']}'. Allowed: " . implode(', ', self::ENGINES) . '.'];
        }
        $params = $sim['params'] ?? null;
        if (! is_array($params)) {
            return ['lab_config.simulation.params must be an object.'];
        }

        $facts = $this->checkEngine($sim['type'], $params);
        if ($this->errors === []) {
            $this->checkSteps($lab['steps'] ?? null, $facts, $sim['type']);
        }
        $this->checkTextLeaves($lab, 'lab_config');

        foreach (['outcomes', 'teacher_script'] as $listKey) {
            if (isset($lab[$listKey])) {
                $this->stringList($lab[$listKey], "lab_config.$listKey", 0, 12, 600);
            }
        }

        return $this->errors;
    }

    // ------------------------------------------------------------------- engines

    /**
     * Validates the parameters and returns the fact names the engine produces.
     *
     * @param  array<string,mixed>  $p
     * @return list<string>
     */
    private function checkEngine(string $type, array $p): array
    {
        return match ($type) {
            'calculator'     => $this->checkCalculator($p),
            'relevance'      => $this->checkRelevance($p),
            'osmosis'        => ['swell', 'shrink', 'nochange', 'change_pct', 'progress', 'has_wall'],
            'variable_model' => $this->checkVariableModel($p),
            'sequence'       => $this->checkSequence($p),
            'classify'       => $this->checkClassify($p),
        };
    }

    /** @return list<string> */
    private function checkCalculator(array $p): array
    {
        $facts = [];
        foreach ($this->list($p['variables'] ?? null, 'variables', 1, 12) as $i => $v) {
            $facts[] = $this->id($v['id'] ?? null, "variables[$i].id");
            $this->label($v['label'] ?? null, "variables[$i].label");
            $this->range($v, "variables[$i]");
        }
        foreach ($this->list($p['outputs'] ?? null, 'outputs', 1, 12) as $i => $o) {
            $this->label($o['label'] ?? null, "outputs[$i].label");
            $this->expr($o['formula'] ?? null, $facts, "outputs[$i].formula");
            $facts[] = $this->id($o['id'] ?? null, "outputs[$i].id");
        }
        $this->conditions($p['warnings'] ?? [], $facts, 'warnings');

        return array_values(array_filter($facts));
    }

    /** @return list<string> */
    private function checkRelevance(array $p): array
    {
        $detailIds = [];
        foreach ($this->list($p['details'] ?? null, 'details', 2, 14) as $i => $d) {
            $detailIds[] = $this->id($d['id'] ?? null, "details[$i].id");
            $this->label($d['label'] ?? null, "details[$i].label");
        }
        $questions = $this->list($p['questions'] ?? null, 'questions', 1, 6);
        foreach ($questions as $i => $q) {
            $this->id($q['id'] ?? null, "questions[$i].id");
            $this->label($q['text'] ?? null, "questions[$i].text");
            foreach ((array) ($q['relevant'] ?? []) as $r) {
                if (! in_array($r, $detailIds, true)) {
                    $this->errors[] = "questions[$i].relevant names an unknown detail '" . (is_scalar($r) ? $r : '?') . "'.";
                }
            }
        }
        $facts = ['kept', 'missed', 'extra', 'complete'];
        foreach ($detailIds as $id) {
            $facts[] = "rel_$id";
            $facts[] = "kept_$id";
        }

        return $facts;
    }

    /** @return list<string> */
    private function checkVariableModel(array $p): array
    {
        $facts = [];
        foreach ($this->list($p['controls'] ?? null, 'controls', 1, 6) as $i => $c) {
            $facts[] = $this->id($c['id'] ?? null, "controls[$i].id");
            $this->label($c['label'] ?? null, "controls[$i].label");
            $this->range($c, "controls[$i]");
            if (isset($c['kind']) && ! in_array($c['kind'], ['slider', 'toggle'], true)) {
                $this->errors[] = "controls[$i].kind must be slider or toggle.";
            }
        }
        foreach ($this->list($p['derived'] ?? [], 'derived', 0, 10) as $i => $d) {
            $this->label($d['label'] ?? null, "derived[$i].label");
            $this->expr($d['formula'] ?? null, $facts, "derived[$i].formula");
            $facts[] = $this->id($d['id'] ?? null, "derived[$i].id");
        }
        $facts = array_values(array_filter($facts));

        $visual = $p['visual'] ?? null;
        if (! is_array($visual) || ! in_array($visual['kind'] ?? null, self::VISUALS, true)) {
            $this->errors[] = 'visual.kind must be one of: ' . implode(', ', self::VISUALS) . '.';
        } else {
            $bind = (array) ($visual['bind'] ?? []);
            $needs = [
                'heating'   => ['temperature', 'heat', 'boiling_point'],
                'particles' => ['energy', 'spacing'],
                'ray'       => ['incidence', 'reflection'],
                'circuit'   => ['closed', 'brightness'],
                'rectangle' => ['width', 'height'],
                'motion'    => ['position', 'speed'],
                'wave'      => ['amplitude', 'frequency'],
                'lever'     => ['left_load', 'left_distance', 'right_load', 'right_distance'],
                'atom'      => ['protons', 'neutrons', 'electrons'],
                'scenery'   => ['sun', 'clouds', 'rain', 'water', 'plants'],
                'mixture'   => ['separated', 'energy'],
                'pendulum'  => ['length', 'swing'],
                'bars'      => [],
            ][$visual['kind']];
            foreach ($needs as $key) {
                $this->expr($bind[$key] ?? null, $facts, "visual.bind.$key");
            }
            if ($visual['kind'] === 'bars') {
                foreach ($this->list($bind['bars'] ?? null, 'visual.bind.bars', 1, 6) as $i => $b) {
                    $this->label($b['label'] ?? null, "visual.bind.bars[$i].label");
                    $this->expr($b['value'] ?? null, $facts, "visual.bind.bars[$i].value");
                    if (isset($b['max']) && ! is_numeric($b['max'])) {
                        $this->errors[] = "visual.bind.bars[$i].max must be a number.";
                    }
                }
            }
        }

        foreach ($this->list($p['observations'] ?? null, 'observations', 1, 10) as $i => $o) {
            $this->expr($o['when'] ?? null, $facts, "observations[$i].when");
            $this->label($o['text'] ?? null, "observations[$i].text", 600);
        }
        $this->conditions($p['warnings'] ?? [], $facts, 'warnings');

        return $facts;
    }

    /** @return list<string> */
    private function checkSequence(array $p): array
    {
        $ids = [];
        foreach ($this->list($p['items'] ?? null, 'items', 2, 10) as $i => $item) {
            $ids[] = $this->id($item['id'] ?? null, "items[$i].id");
            $this->label($item['label'] ?? null, "items[$i].label");
        }
        $this->unique($ids, 'items');

        return ['correct', 'total', 'in_order'];
    }

    /** @return list<string> */
    private function checkClassify(array $p): array
    {
        $cats = [];
        foreach ($this->list($p['categories'] ?? null, 'categories', 2, 5) as $i => $c) {
            $cats[] = $this->id($c['id'] ?? null, "categories[$i].id");
            $this->label($c['label'] ?? null, "categories[$i].label");
        }
        $ids = [];
        foreach ($this->list($p['items'] ?? null, 'items', 2, 12) as $i => $item) {
            $ids[] = $this->id($item['id'] ?? null, "items[$i].id");
            $this->label($item['label'] ?? null, "items[$i].label");
            if (! in_array($item['category'] ?? null, $cats, true)) {
                $this->errors[] = "items[$i].category must name one of the categories.";
            }
        }
        $this->unique($ids, 'items');
        $this->unique($cats, 'categories');

        return ['correct', 'wrong', 'unassigned', 'total', 'complete'];
    }

    // --------------------------------------------------------------------- steps

    /**
     * @param  mixed  $steps
     * @param  list<string>  $facts
     */
    private function checkSteps($steps, array $facts, string $type): void
    {
        if (! is_array($steps)) {
            $this->errors[] = 'lab_config.steps is required.';

            return;
        }
        foreach (self::STEP_KEYS as $key) {
            if (! isset($steps[$key]) || ! is_array($steps[$key])) {
                $this->errors[] = "steps.$key is required.";
            }
        }
        if ($this->errors !== []) {
            return;
        }

        $this->label($steps['mission']['scenario'] ?? null, 'steps.mission.scenario', 1500);
        $this->label($steps['mission']['task'] ?? null, 'steps.mission.task', 600);

        $this->label($steps['predict']['question'] ?? null, 'steps.predict.question', 600);
        $predict = $this->list($steps['predict']['options'] ?? null, 'steps.predict.options', 2, 5);
        $this->options($predict, 'steps.predict.options', false);
        foreach ($predict as $i => $o) {
            $this->expr($o['when'] ?? null, $facts, "steps.predict.options[$i].when");
        }
        if (isset($steps['predict']['scenario']) && ! is_array($steps['predict']['scenario'])) {
            $this->errors[] = 'steps.predict.scenario must be an object.';
        }

        $this->stringList($steps['do']['instructions'] ?? null, 'steps.do.instructions', 1, 10, 600);
        $this->label($steps['observe']['prompt'] ?? null, 'steps.observe.prompt', 600);
        $this->label($steps['explain']['text'] ?? null, 'steps.explain.text', 1500);
        $this->conditions($steps['explain']['cases'] ?? [], $facts, 'steps.explain.cases');
        $this->label($steps['concept']['text'] ?? null, 'steps.concept.text', 1500);
        $this->stringList($steps['concept']['points'] ?? [], 'steps.concept.points', 0, 8, 600);

        $this->label($steps['apply']['question'] ?? null, 'steps.apply.question', 600);
        $apply = $this->list($steps['apply']['options'] ?? null, 'steps.apply.options', 2, 5);
        $this->options($apply, 'steps.apply.options', true);
        $correct = count(array_filter($apply, fn ($o) => ($o['correct'] ?? false) === true));
        if ($correct !== 1) {
            $this->errors[] = 'steps.apply.options must have exactly one correct option.';
        }

        $this->stringList($steps['reflect']['prompts'] ?? null, 'steps.reflect.prompts', 1, 4, 600);
    }

    /** @param list<array<string,mixed>> $options */
    private function options(array $options, string $path, bool $needsFeedback): void
    {
        $ids = [];
        foreach ($options as $i => $o) {
            $ids[] = $this->id($o['id'] ?? null, "{$path}[$i].id");
            $this->label($o['label'] ?? null, "{$path}[$i].label");
            if ($needsFeedback) {
                $this->label($o['feedback'] ?? null, "{$path}[$i].feedback", 600);
            }
        }
        $this->unique($ids, $path);
    }

    // ------------------------------------------------------------------- helpers

    /**
     * @param  mixed  $value
     * @return list<array<string,mixed>>
     */
    private function list($value, string $path, int $min, int $max): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            if ($min > 0 || $value !== null) {
                $this->errors[] = "$path must be a list.";
            }

            return [];
        }
        if (count($value) < $min || count($value) > $max) {
            $this->errors[] = "$path must have between $min and $max entries.";
        }
        $out = [];
        foreach (array_slice($value, 0, $max) as $i => $row) {
            if (! is_array($row)) {
                $this->errors[] = "{$path}[$i] must be an object.";
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @param mixed $value */
    private function stringList($value, string $path, int $min, int $max, int $len): void
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) < $min || count($value) > $max) {
            $this->errors[] = "$path must be a list of $min to $max strings.";

            return;
        }
        foreach ($value as $i => $s) {
            $this->label($s, "{$path}[$i]", $len);
        }
    }

    /** @param mixed $value */
    private function id($value, string $path): string
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', $value)) {
            $this->errors[] = "$path must be an identifier (letters, digits, underscore; max 32).";

            return '';
        }

        return $value;
    }

    /** @param mixed $value */
    private function label($value, string $path, int $max = 300): void
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
            $this->errors[] = "$path must be non-empty text of at most $max characters.";
        }
    }

    /** @param array<string,mixed> $row */
    private function range(array $row, string $path): void
    {
        foreach (['min', 'max', 'step', 'default'] as $k) {
            if (! isset($row[$k]) || ! is_numeric($row[$k])) {
                $this->errors[] = "$path.$k must be a number.";

                return;
            }
        }
        if ($row['min'] >= $row['max'] || $row['step'] <= 0 || $row['default'] < $row['min'] || $row['default'] > $row['max']) {
            $this->errors[] = "$path needs min < max, step > 0 and default inside [min, max].";
        }
    }

    /** @param list<string> $ids */
    private function unique(array $ids, string $path): void
    {
        $ids = array_filter($ids);
        if (count($ids) !== count(array_unique($ids))) {
            $this->errors[] = "$path has duplicate ids.";
        }
    }

    /**
     * @param  mixed  $rows
     * @param  list<string>  $facts
     */
    private function conditions($rows, array $facts, string $path): void
    {
        foreach ($this->list($rows ?? [], $path, 0, 10) as $i => $row) {
            $this->expr($row['when'] ?? null, $facts, "{$path}[$i].when");
            $this->label($row['text'] ?? null, "{$path}[$i].text", 1500);
        }
    }

    /**
     * An expression must use only the expression language's characters, stay short, and name
     * only known facts.
     *
     * @param  mixed  $expr
     * @param  list<string>  $facts
     */
    private function expr($expr, array $facts, string $path): void
    {
        if (! is_string($expr) || trim($expr) === '' || strlen($expr) > 200) {
            $this->errors[] = "$path must be an expression of at most 200 characters.";

            return;
        }
        if (! preg_match('/^[A-Za-z0-9_.\s+\-*\/()<>=!&|]+$/', $expr)) {
            $this->errors[] = "$path contains characters outside the expression language.";

            return;
        }
        if (preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $expr, $m)) {
            foreach (array_unique($m[0]) as $name) {
                if (! in_array($name, $facts, true)) {
                    $this->errors[] = "$path uses '$name', which is not a value this simulation provides.";
                }
            }
        }
    }

    /** No markup anywhere: lab text is rendered as plain text, and stays that way. */
    private function checkTextLeaves(mixed $node, string $path): void
    {
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $this->checkTextLeaves($v, "$path.$k");
            }
        } elseif (is_string($node) && preg_match('/<[a-zA-Z\/!]|javascript:/i', $node) && ! str_contains($path, 'formula') && ! str_contains($path, '.when')) {
            $this->errors[] = "$path must be plain text (no HTML or script).";
        }
    }
}
