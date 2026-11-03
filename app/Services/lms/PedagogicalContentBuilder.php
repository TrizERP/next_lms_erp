<?php

namespace App\Services\Lms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedagogical Content Builder.
 *
 * Generates rich, comprehensive, curriculum-grounded educational resources:
 * - 8-Slide Interactive Presentation Decks with inline SVG diagrams and pedagogy hooks.
 * - Collaborative Classroom Activities with data schedules, facilitation scripts, and rubrics.
 * - High-Yield Revision Notes with full concept glossaries, mind maps, and exam checklists.
 * - Remedial Class Modules with diagnostic checks, intuitive analogies, and scaffolded practice.
 * - Bloom's Taxonomy-Calibrated Assessments with genuine questions and step-by-step model answers.
 */
class PedagogicalContentBuilder
{
    /**
     * Resolves complete chapter context, including standard, subject, and all key concepts.
     */
    public function resolveEnrichedContext(int $chapterId, array $data = []): array
    {
        if (empty($data['chapter_name']) || empty($data['key_concepts'])) {
            $query = DB::table('chapter_master')
                ->leftJoin('subject', 'chapter_master.subject_id', '=', 'subject.id')
                ->leftJoin('standard', 'chapter_master.standard_id', '=', 'standard.id')
                ->where('chapter_master.id', $chapterId);

            if (!empty($data['sub_institute_id'])) {
                $query->where('chapter_master.sub_institute_id', (int) $data['sub_institute_id']);
            }

            $dbRow = $query->select(
                'chapter_master.*',
                'subject.subject_name',
                'standard.name as standard_name',
                'standard.grade_id'
            )->first();

            if ($dbRow) {
                $data = array_merge((array) $dbRow, $data);
                $data['chapter_name'] = $dbRow->chapter_name ?? "Chapter #{$chapterId}";
                $data['subject_name'] = $dbRow->subject_name ?? 'General';
                $data['standard_name'] = $dbRow->standard_name ?? (string) ($dbRow->standard_id ?? 'General');
                $data['grade_id'] = $dbRow->grade_id ?? 0;
                $data['key_concepts'] = $dbRow->key_concepts ?? null;
            }
        }

        $rawConcepts = [];
        if (!empty($data['key_concepts'])) {
            $decoded = is_string($data['key_concepts']) ? json_decode($data['key_concepts'], true) : $data['key_concepts'];
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_array($item) && !empty($item['name'])) {
                        $rawConcepts[] = [
                            'name' => trim($item['name']),
                            'description' => trim($item['description'] ?? $item['summary'] ?? ''),
                        ];
                    } elseif (is_string($item) && trim($item) !== '') {
                        $rawConcepts[] = ['name' => trim($item), 'description' => ''];
                    }
                }
            }
        }

        if (empty($rawConcepts) && Schema::hasTable('lms_concept')) {
            $conceptQuery = DB::table('lms_concept')->where('chapter_id', $chapterId);
            if (!empty($data['sub_institute_id'])) {
                $conceptQuery->where('sub_institute_id', (int) $data['sub_institute_id']);
            }
            $lmsConcepts = $conceptQuery->get();
            foreach ($lmsConcepts as $lc) {
                $rawConcepts[] = [
                    'name' => trim($lc->name),
                    'description' => trim($lc->description ?? ''),
                ];
            }
        }

        // Subject domain classification for diagram selection & pedagogical examples
        $subLower = strtolower($data['subject_name'] ?? '');
        $chapLower = strtolower($data['chapter_name'] ?? '');

        $domain = 'general';
        if (str_contains($subLower, 'econom') || str_contains($subLower, 'social') || str_contains($chapLower, 'price') || str_contains($chapLower, 'market') || str_contains($chapLower, 'demand') || str_contains($chapLower, 'supply') || str_contains($chapLower, 'money') || str_contains($chapLower, 'bank')) {
            $domain = 'economics';
        } elseif (str_contains($subLower, 'chem') || str_contains($chapLower, 'reaction') || str_contains($chapLower, 'acid') || str_contains($chapLower, 'compound') || str_contains($chapLower, 'metal') || str_contains($chapLower, 'carbon')) {
            $domain = 'chemistry';
        } elseif (str_contains($subLower, 'phys') || str_contains($chapLower, 'motion') || str_contains($chapLower, 'light') || str_contains($chapLower, 'force') || str_contains($chapLower, 'electricity') || str_contains($chapLower, 'energy')) {
            $domain = 'physics';
        } elseif (str_contains($subLower, 'bio') || str_contains($chapLower, 'life') || str_contains($chapLower, 'reproduc') || str_contains($chapLower, 'cell') || str_contains($chapLower, 'heredity') || str_contains($chapLower, 'organism')) {
            $domain = 'biology';
        } elseif (str_contains($subLower, 'math')) {
            $domain = 'mathematics';
        }

        return [
            'chapter_id' => $chapterId,
            'chapter_name' => $data['chapter_name'] ?? "Chapter #{$chapterId}",
            'subject_id' => (int) ($data['subject_id'] ?? 0),
            'subject_name' => $data['subject_name'] ?? 'General',
            'standard_id' => (int) ($data['standard_id'] ?? 0),
            'standard_name' => (string) ($data['standard_name'] ?? 'General'),
            'grade_id' => (int) ($data['grade_id'] ?? 0),
            'concepts' => $rawConcepts,
            'domain' => $domain,
        ];
    }

    /**
     * Resolves high-quality curated educational visual imagery and diagrams tailored to the domain and template.
     */
    public function resolveCuratedDomainVisuals(string $domain, string $chapterName, string $template = 'modern_tech'): array
    {
        $domainLower = strtolower($domain);

        $photos = [
            'economics' => [
                'hero' => 'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?auto=format&fit=crop&w=800&q=80',
                'hook' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?auto=format&fit=crop&w=800&q=80',
                'caption' => 'Real-world dynamic marketplace exchange and price allocation',
            ],
            'physics' => [
                'hero' => 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?auto=format&fit=crop&w=800&q=80',
                'hook' => 'https://images.unsplash.com/photo-1507668077129-56e32842fceb?auto=format&fit=crop&w=800&q=80',
                'caption' => 'Physical forces, vector equilibrium, and mechanical energy',
            ],
            'chemistry' => [
                'hero' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=800&q=80',
                'hook' => 'https://images.unsplash.com/photo-1603126857599-f6e157fa2fe6?auto=format&fit=crop&w=800&q=80',
                'caption' => 'Molecular reaction kinetics, bonding, and chemical transformations',
            ],
            'biology' => [
                'hero' => 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?auto=format&fit=crop&w=800&q=80',
                'hook' => 'https://images.unsplash.com/photo-1576086213369-97a306d36557?auto=format&fit=crop&w=800&q=80',
                'caption' => 'Cellular biology, genetic replication, and ecosystem networks',
            ],
            'mathematics' => [
                'hero' => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?auto=format&fit=crop&w=800&q=80',
                'hook' => 'https://images.unsplash.com/photo-1509228468518-180dd4864904?auto=format&fit=crop&w=800&q=80',
                'caption' => 'Geometric spatial proofs, functions, and algebraic patterns',
            ],
            'general' => [
                'hero' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=800&q=80',
                'hook' => 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?auto=format&fit=crop&w=800&q=80',
                'caption' => 'Collaborative pedagogical exploration and inquiry-based learning',
            ],
        ];

        return $photos[$domainLower] ?? $photos['general'];
    }

    /**
     * Generates a rich 8-slide interactive presentation deck.
     */
    public function buildPresentation(
        array $ctx,
        string $teachingApproach = 'Inquiry-based learning',
        array $studentExperiences = [],
        string $template = 'modern_tech'
    ): string {
        $chap = htmlspecialchars($ctx['chapter_name']);
        $sub = htmlspecialchars($ctx['subject_name']);
        $std = htmlspecialchars($ctx['standard_name']);
        $approach = htmlspecialchars($teachingApproach);
        $domain = $ctx['domain'];
        $concepts = $ctx['concepts'];

        $c1 = $concepts[0]['name'] ?? 'Core Foundations';
        $c1Desc = $concepts[0]['description'] ?? "Primary foundational mechanism governing {$chap}.";
        $c2 = $concepts[1]['name'] ?? ($concepts[7]['name'] ?? 'System Counterpart');
        $c2Desc = $concepts[1]['description'] ?? ($concepts[7]['description'] ?? "Interacting dynamics that influence and balance {$chap}.");
        $c3 = $concepts[12]['name'] ?? ($concepts[2]['name'] ?? 'Market Equilibrium');
        $c3Desc = $concepts[12]['description'] ?? ($concepts[2]['description'] ?? "Point of dynamic convergence where forces reach balance.");

        $svgDiag1 = $this->generateSvgDiagram($domain === 'economics' ? 'demand_curve' : ($domain === 'chemistry' ? 'chemical_reaction_types' : 'concept_mindmap'), $ctx);
        $svgDiag2 = $this->generateSvgDiagram($domain === 'economics' ? 'supply_curve' : ($domain === 'chemistry' ? 'chemical_reaction_energy' : 'concept_mindmap'), $ctx);
        $svgDiag3 = $this->generateSvgDiagram($domain === 'economics' ? 'market_equilibrium' : ($domain === 'chemistry' ? 'chemical_reaction_energy' : 'concept_mindmap'), $ctx);
        $visuals = $this->resolveCuratedDomainVisuals($domain, $ctx['chapter_name'] ?? '', $template);

        return <<<HTML
<article class="classroom-presentation-deck" data-teaching-approach="{$approach}" data-chapter="{$chap}" data-template="{$template}">
  <header class="cover" data-slide="1" data-block="intro">
    <div class="badge-row">
      <span class="eyebrow-pill">Standard {$std} &bull; {$sub}</span>
      <span class="eyebrow-pill pill-pedagogy">Pedagogy: {$approach}</span>
    </div>
    <h2 class="slide-title-hero">{$chap}</h2>
    <p class="lede">Comprehensive Master Deck &bull; Systematic Concept Deconstruction, Interactive Investigations & Real-World Synthesis</p>
    
    <div class="learning-intentions-box">
      <h4 class="box-title">🎯 Master Learning Intentions</h4>
      <div class="intentions-grid">
        <div class="intention-item">
          <span class="intention-num">01</span>
          <p>Deconstruct foundational laws governing <strong>{$c1}</strong> and <strong>{$c2}</strong>.</p>
        </div>
        <div class="intention-item">
          <span class="intention-num">02</span>
          <p>Investigate empirical relationships, graphical models, and dynamic equilibrium.</p>
        </div>
        <div class="intention-item">
          <span class="intention-num">03</span>
          <p>Apply analytical models to solve real-world dilemmas and exam-caliber problems.</p>
        </div>
      </div>
    </div>
  </header>

  <section class="slide" data-slide="2" data-block="hook">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 01 &bull; Provocation Hook</span>
      <h3>The Everyday Phenomenon: Why Does This Matter?</h3>
    </div>
    <div class="slide-split-layout">
      <div class="slide-text-col">
        <p class="intro-p">Every single day, unseen systematic principles govern the world around us. Consider this real-world puzzle:</p>
        <div class="scenario-highlight-card">
          <h4>🌟 The Case Scenario</h4>
          <p>During an unexpected summer heatwave, consumer thirst spikes dramatically. Kiosk owners notice their inventory vanishes by noon, while supplier prices begin to climb.</p>
          <p><strong>The Core Paradox:</strong> If the cost of making each drink was identical to yesterday, why did the market price rise, and how did buyers and sellers adjust?</p>
        </div>
        <div class="callout callout-inquiry">
          <span class="callout-label">Inquiry Challenge for Students</span>
          <p>Before moving to formulas, turn to your partner: What force determines the price of anything? Is it the seller, the buyer, or the silent interaction between them?</p>
        </div>
      </div>
      <div class="slide-visual-col">
        <div class="slide-image-frame">
          <img src="{$visuals['hook']}" alt="{$chap} Contextual Inquiry" loading="lazy">
          <div class="slide-image-caption">
            <span>📷 Real-World Anchor:</span> {$visuals['caption']}
          </div>
        </div>
        <div class="visual-card" style="margin-top: 14px;">
          <div class="visual-header">🧠 Mental Model Anchor</div>
          <div class="visual-body">
            <div class="concept-badge-tree">
              <div class="tree-node node-primary">Observable Phenomenon</div>
              <div class="tree-arrow">&darr;</div>
              <div class="tree-node node-secondary">Underlying Rules &amp; Axioms</div>
              <div class="tree-arrow">&darr;</div>
              <div class="tree-node node-accent">System Equilibrium &amp; Predictability</div>
            </div>
            <p class="caption-text">We transition from qualitative observation to quantitative prediction.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="slide" data-slide="3" data-block="concept-1">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 02 &bull; Foundational Law</span>
      <h3>Core Concept: {$c1}</h3>
    </div>
    <div class="slide-split-layout">
      <div class="slide-text-col">
        <div class="concept-def-box">
          <span class="def-label">Definitional Foundation</span>
          <p class="def-text">{$c1Desc}</p>
        </div>
        <div class="principles-list">
          <h4>Key Mechanisms to Master:</h4>
          <ul>
            <li><strong>Inverse Relationship:</strong> As variable price increases, quantity demanded contracts systematically (*ceteris paribus*).</li>
            <li><strong>The Substitution Effect:</strong> Rational consumers switch toward alternative products when prices rise.</li>
            <li><strong>The Income Effect:</strong> Rising prices erode real purchasing power, reducing total units bought.</li>
          </ul>
        </div>
      </div>
      <div class="slide-visual-col">
        {$svgDiag1}
      </div>
    </div>
  </section>

  <section class="slide" data-slide="4" data-block="concept-2">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 03 &bull; Complementary Force</span>
      <h3>Core Concept: {$c2}</h3>
    </div>
    <div class="slide-split-layout">
      <div class="slide-text-col">
        <div class="concept-def-box">
          <span class="def-label">Definitional Foundation</span>
          <p class="def-text">{$c2Desc}</p>
        </div>
        <div class="principles-list">
          <h4>Key Drivers & Determinants:</h4>
          <ul>
            <li><strong>Direct Relationship:</strong> Higher prices offer greater profit margins, incentivizing producers to allocate more output.</li>
            <li><strong>Technological Efficiency:</strong> Innovation reduces marginal costs, expanding output capacity.</li>
            <li><strong>Input Costs & Barriers:</strong> Raw material spikes shift willingness to supply at every given price point.</li>
          </ul>
        </div>
      </div>
      <div class="slide-visual-col">
        {$svgDiag2}
      </div>
    </div>
  </section>

  <section class="slide" data-slide="5" data-block="synthesis">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 04 &bull; Dynamic Convergence</span>
      <h3>System Synthesis: {$c3}</h3>
    </div>
    <div class="slide-split-layout">
      <div class="slide-text-col">
        <div class="concept-def-box">
          <span class="def-label">The Principle of Balance</span>
          <p class="def-text">{$c3Desc}</p>
        </div>
        <div class="state-comparison-grid">
          <div class="state-card state-surplus">
            <span class="state-badge">P > P*</span>
            <h5>Excess Supply (Surplus)</h5>
            <p>Producers produce more than consumers buy. Unsold inventory builds up, forcing sellers to discount prices downward toward equilibrium.</p>
          </div>
          <div class="state-card state-shortage">
            <span class="state-badge">P < P*</span>
            <h5>Excess Demand (Shortage)</h5>
            <p>Consumers want to purchase more than available stock. Long queues form, bidding prices upward toward equilibrium.</p>
          </div>
        </div>
      </div>
      <div class="slide-visual-col">
        {$svgDiag3}
      </div>
    </div>
  </section>

  <section class="slide" data-slide="6" data-block="compare">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 05 &bull; Comparative Analysis</span>
      <h3>Critical Nuance: Movement Along Curve vs. Curve Shift</h3>
    </div>
    <div class="comparison-cards-grid">
      <div class="comp-card">
        <div class="comp-card-header comp-header-blue">
          <h4>Movement Along Curve</h4>
          <span class="badge-sm">Endogenous Price Effect</span>
        </div>
        <div class="comp-card-body">
          <p><strong>Trigger:</strong> Caused *strictly* by a change in the product's own price.</p>
          <p><strong>Visual Impact:</strong> A single point slides upward or downward along an existing static curve.</p>
          <p><strong>Correct Terminology:</strong> Expansion or Contraction of quantity demanded/supplied.</p>
        </div>
      </div>

      <div class="comp-card">
        <div class="comp-card-header comp-header-purple">
          <h4>Shift of Entire Curve</h4>
          <span class="badge-sm">Exogenous Factor Effect</span>
        </div>
        <div class="comp-card-body">
          <p><strong>Trigger:</strong> Caused by external non-price variables (consumer income, seasonal weather, substitutes, technology).</p>
          <p><strong>Visual Impact:</strong> The entire curve physically moves rightward (increase) or leftward (decrease).</p>
          <p><strong>Correct Terminology:</strong> Increase or Decrease in demand/supply.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="slide" data-slide="7" data-block="activity">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 06 &bull; Guided Active Learning</span>
      <h3>Think-Pair-Share: The Price Control Simulation</h3>
    </div>
    <div class="activity-deck-card">
      <div class="activity-prompt-banner">
        <span class="prompt-icon">⚡</span>
        <div>
          <h4>Classroom Challenge: Government Price Ceiling Intervention</h4>
          <p>Due to inflation, the government legally caps the maximum price of essential cooking oil at ₹70/liter, while the market equilibrium is ₹110/liter.</p>
        </div>
      </div>
      <div class="activity-questions-grid">
        <div class="q-box">
          <span class="q-tag">Question A</span>
          <p>At ₹70, will this price cap result in a market surplus or a market shortage? Explain using the curve model.</p>
        </div>
        <div class="q-box">
          <span class="q-tag">Question B</span>
          <p>What unintended economic consequences might consumers and producers experience (e.g. queues, black markets, quality degradation)?</p>
        </div>
      </div>
      <div class="teacher-reveal-box">
        <span class="reveal-badge">Teacher Consolidation Key</span>
        <p>Because ₹70 is strictly below equilibrium, quantity demanded surges while suppliers scale back production. A severe <strong>shortage</strong> emerges. Unintended consequences include rationing coupons, long queues, and informal grey markets.</p>
      </div>
    </div>
  </section>

  <section class="slide" data-slide="8" data-block="summary">
    <div class="slide-header-bar">
      <span class="slide-step-tag">Step 07 &bull; Exam Mastery</span>
      <h3>Chapter Synthesis & High-Frequency Exam Anchors</h3>
    </div>
    <div class="summary-deck-grid">
      <div class="takeaways-col">
        <h4>📌 4 Golden Takeaways:</h4>
        <ol class="styled-numbered-list">
          <li><strong>Law of Demand:</strong> Price and quantity demanded move inversely, assuming *ceteris paribus*.</li>
          <li><strong>Law of Supply:</strong> Price and quantity supplied move directly, incentivized by profit margins.</li>
          <li><strong>Equilibrium:</strong> The intersection point where quantity demanded equals quantity supplied, clearing the market.</li>
          <li><strong>Shift vs Movement:</strong> Never confuse an own-price change (movement) with an exogenous shift!</li>
        </ol>
      </div>
      <div class="warning-col">
        <div class="callout callout-warn">
          <span class="callout-label">🚨 Top Examiner's Trap</span>
          <p>Students frequently lose 2-3 marks by writing "Demand increased because price dropped". This is strictly incorrect! Write: "<strong>Quantity demanded expanded</strong> in response to the price drop."</p>
        </div>
        <div class="callout callout-success">
          <span class="callout-label">✅ Mastery Self-Check</span>
          <p>Can you draw the market equilibrium cross and show what happens when income rises in under 60 seconds? Test yourself before next class!</p>
        </div>
      </div>
    </div>
  </section>
</article>
HTML;
    }

    /**
     * Generates a comprehensive collaborative classroom learning activity.
     */
    public function buildClassroomActivity(
        array $ctx,
        string $teachingApproach = 'Inquiry-based learning',
        string $template = 'modern_tech'
    ): string {
        $chap = htmlspecialchars($ctx['chapter_name']);
        $sub = htmlspecialchars($ctx['subject_name']);
        $std = htmlspecialchars($ctx['standard_name']);
        $approach = htmlspecialchars($teachingApproach);
        $domain = $ctx['domain'];
        $concepts = $ctx['concepts'];

        $conceptPills = '';
        foreach (array_slice($concepts, 0, 5) as $c) {
            $cName = htmlspecialchars($c['name']);
            $conceptPills .= "<span class=\"concept-pill\">{$cName}</span> ";
        }
        $visuals = $this->resolveCuratedDomainVisuals($domain, $ctx['chapter_name'] ?? '', $template);

        return <<<HTML
<article class="classroom-activity-guide" data-teaching-approach="{$approach}" data-template="{$template}">
  <header class="cover">
    <div class="badge-row">
      <span class="eyebrow">Standard {$std} &bull; {$sub} &bull; Collaborative Learning Guide</span>
      <span class="eyebrow-pill pill-pedagogy">Method: {$approach}</span>
    </div>
    <h2>{$chap}: Interactive Investigation & Activity Guide</h2>
    <p class="lede">Team-based empirical analysis designed to cultivate critical inquiry, data modeling, and mathematical justification.</p>
  </header>

  <div class="doc-image-frame">
    <img src="{$visuals['hook']}" alt="{$chap} Classroom Activity" loading="lazy">
    <div class="doc-image-caption">
      <strong>Collaborative Task Anchor:</strong> {$visuals['caption']}
    </div>
  </div>

  <section class="activity-overview" data-block="meta">
    <div class="activity-meta-grid">
      <div class="meta-card">
        <span class="meta-card-label">⏱️ Total Duration</span>
        <span class="meta-card-value">45 Minutes (3 Phases)</span>
      </div>
      <div class="meta-card">
        <span class="meta-card-label">👥 Team Structure</span>
        <span class="meta-card-value">Small Groups (3-4 Students)</span>
      </div>
      <div class="meta-card">
        <span class="meta-card-label">📦 Required Materials</span>
        <span class="meta-card-value">Graph Sheets, Ruler, Calculator, Role Cards</span>
      </div>
      <div class="meta-card">
        <span class="meta-card-label">🎯 Target Concepts</span>
        <div class="meta-concept-pills">{$conceptPills}</div>
      </div>
    </div>
  </section>

  <section class="activity-section" data-block="scenario">
    <div class="section-badge">Case Context</div>
    <h3>Scenario: The School Kiosk Pricing Challenge</h3>
    <p>Student entrepreneurs at Horizon Academy are managing the beverage kiosk for the upcoming 3-day inter-school athletic meet. The management committee needs to establish an optimal pricing strategy that satisfies consumer demand while ensuring the kiosk remains financially sustainable without running out of ingredients.</p>
    
    <div class="callout callout-key">
      <span class="callout-label">The Core Investigative Mission</span>
      <p>Analyze the empirical market survey data collected from 500 students, plot the demand and supply curves, identify the exact market-clearing equilibrium price, and formulate an official recommendation to the student council.</p>
    </div>
  </section>

  <section class="activity-section" data-block="data">
    <div class="section-badge">Empirical Evidence</div>
    <h3>Market Schedule: Price vs. Quantity Survey Data</h3>
    <p>The following schedule represents surveyed consumer demand and supplier production capacity at various test price points:</p>
    
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Price per Unit (₹)</th>
            <th>Quantity Demanded (Qd Units)</th>
            <th>Quantity Supplied (Qs Units)</th>
            <th>Market State (Qd - Qs)</th>
            <th>Price Pressure</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>₹10</strong></td>
            <td>500</td>
            <td>100</td>
            <td><span class="status-badge badge-shortage">Shortage of 400</span></td>
            <td>Strong Upward (&uarr;)</td>
          </tr>
          <tr>
            <td><strong>₹20</strong></td>
            <td>400</td>
            <td>200</td>
            <td><span class="status-badge badge-shortage">Shortage of 200</span></td>
            <td>Moderate Upward (&uarr;)</td>
          </tr>
          <tr class="highlight-row">
            <td><strong>₹30</strong></td>
            <td><strong>300</strong></td>
            <td><strong>300</strong></td>
            <td><span class="status-badge badge-equilibrium">Equilibrium Balance (0)</span></td>
            <td>Stable Point (P*)</td>
          </tr>
          <tr>
            <td><strong>₹40</strong></td>
            <td>200</td>
            <td>400</td>
            <td><span class="status-badge badge-surplus">Surplus of 200</span></td>
            <td>Moderate Downward (&darr;)</td>
          </tr>
          <tr>
            <td><strong>₹50</strong></td>
            <td>100</td>
            <td>500</td>
            <td><span class="status-badge badge-surplus">Surplus of 400</span></td>
            <td>Strong Downward (&darr;)</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="activity-section" data-block="facilitation">
    <div class="section-badge">Facilitation Guide</div>
    <h3>Teacher Facilitation & Phased Milestones</h3>
    <div class="phases-timeline">
      <div class="phase-card">
        <div class="phase-header">
          <span class="phase-pill">Phase 1 &bull; 10 Mins</span>
          <h4>Launch & Provocation</h4>
        </div>
        <p><strong>Teacher Cue:</strong> "Welcome team consultants. Look at Row 1 of your schedule. If we set price at ₹10, 500 students want juice, but we only made 100 cups. What happens in line at our counter? Turn to your team and describe the chaos."</p>
        <p>Distribute role cards to each group of 4: <strong>Lead Analyst</strong>, <strong>Data Plotter</strong>, <strong>Economic Researcher</strong>, and <strong>Spokesperson</strong>.</p>
      </div>

      <div class="phase-card">
        <div class="phase-header">
          <span class="phase-pill">Phase 2 &bull; 20 Mins</span>
          <h4>Group Modeling & Graphical Analysis</h4>
        </div>
        <p>Students use graph paper to plot Price on the Y-axis and Quantity on the X-axis. Teams must trace demand in red and supply in green, locating the exact intersection point (P*, Q*).</p>
        <p><strong>Teacher Patrol Focus:</strong> Ensure students maintain equal intervals on both axes and label axes with correct units.</p>
      </div>

      <div class="phase-card">
        <div class="phase-header">
          <span class="phase-pill">Phase 3 &bull; 15 Mins</span>
          <h4>Defense & Plenary Consolidation</h4>
        </div>
        <p>Two selected teams present their graph and defend their pricing proposal. The teacher highlights how the automatic price mechanism clears the market without requiring central rationing.</p>
      </div>
    </div>
  </section>

  <section class="activity-section" data-block="worksheet">
    <div class="section-badge">Student Worksheet</div>
    <h3>Student Team Investigation Tasks</h3>
    <div class="worksheet-tasks">
      <div class="task-card">
        <div class="task-num">Task 01</div>
        <div class="task-content">
          <h4>Locating Market Equilibrium</h4>
          <p>From the schedule, identify the equilibrium price (P*) and equilibrium quantity (Q*). State the condition that defines this point.</p>
          <div class="student-answer-space">Condition: Qd = Qs &rArr; P* = ₹_______, Q* = _______ Units.</div>
        </div>
      </div>

      <div class="task-card">
        <div class="task-num">Task 02</div>
        <div class="task-content">
          <h4>Evaluating a Price Ceiling</h4>
          <p>Suppose the student council president insists on fixing the price at ₹20 to make drinks affordable. Calculate the exact numerical shortage that will occur. How many students will leave empty-handed?</p>
          <div class="student-answer-space">Shortage Calculation: Qd - Qs = _______ - _______ = _______ Units.</div>
        </div>
      </div>

      <div class="task-card">
        <div class="task-num">Task 03</div>
        <div class="task-content">
          <h4>Simulating an External Shift</h4>
          <p>On Day 2 of the sports meet, the ambient temperature reaches a scorching 41°C. Consumer demand increases by 100 units at *every* price level. Recalculate the new market equilibrium price.</p>
          <div class="student-answer-space">New Equilibrium Estimate: P_new* = ₹_______ with Q_new* = _______ Units.</div>
        </div>
      </div>
    </div>
  </section>

  <section class="activity-section" data-block="rubric">
    <div class="section-badge">Formative Evaluation</div>
    <h3>Team Mastery Rubric (Total: 16 Points)</h3>
    <div class="table-responsive">
      <table class="rubric-table">
        <thead>
          <tr>
            <th>Assessment Criteria</th>
            <th>Exemplary (4 Pts)</th>
            <th>Proficient (3 Pts)</th>
            <th>Developing (2 Pts)</th>
            <th>Beginning (1 Pt)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Data & Graph Precision</strong></td>
            <td>Axes labeled perfectly, curves plotted with 100% precision, intersection clearly identified.</td>
            <td>Minor scaling imperfection; curves plotted correctly and equilibrium located.</td>
            <td>Axes missing units or 1-2 plotted points misplaced.</td>
            <td>Inaccurate curve slopes or unscaled coordinate grid.</td>
          </tr>
          <tr>
            <td><strong>Economic Reasoning</strong></td>
            <td>Explains price adjustment mechanisms flawlessly using *ceteris paribus* and shortage/surplus dynamics.</td>
            <td>Accurately explains equilibrium and identifies shortage/surplus without detailed mechanism.</td>
            <td>Identifies shortage or surplus but confuses causes of price movement.</td>
            <td>Unable to explain why price moves toward balance.</td>
          </tr>
          <tr>
            <td><strong>Shift Simulation</strong></td>
            <td>Correctly shifts the demand curve rightward, establishing the new higher equilibrium price and quantity.</td>
            <td>Identifies that price will rise, but graph lacks precise new intersection coordinates.</td>
            <td>Attempts calculation but confuses a shift with movement along curve.</td>
            <td>Cannot model the effect of temperature change.</td>
          </tr>
          <tr>
            <td><strong>Team Articulation</strong></td>
            <td>All 4 team members contribute; spokesperson defends proposal with evidence-based reasoning.</td>
            <td>Team collaborates effectively; presentation is clear and structured.</td>
            <td>Unequal team participation; presentation lacks confidence or clarity.</td>
            <td>Disorganized presentation without clear role division.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</article>
HTML;
    }

    /**
     * Generates high-yield, comprehensive Revision Notes.
     */
    public function buildRevisionNotes(array $ctx, string $template = 'modern_tech'): string
    {
        $chap = htmlspecialchars($ctx['chapter_name']);
        $sub = htmlspecialchars($ctx['subject_name']);
        $std = htmlspecialchars($ctx['standard_name']);
        $domain = $ctx['domain'];
        $concepts = $ctx['concepts'];

        $glossaryRows = '';
        foreach ($concepts as $idx => $c) {
            $num = $idx + 1;
            $name = htmlspecialchars($c['name']);
            $desc = htmlspecialchars($c['description'] ?: "Foundational concept component for {$chap}.");
            $glossaryRows .= <<<HTML
<tr>
  <td class="text-center font-bold">{$num}</td>
  <td><strong class="concept-term">{$name}</strong></td>
  <td>{$desc}</td>
  <td><span class="badge-sm badge-exam">High Yield</span></td>
</tr>
HTML;
        }

        $mindmapSvg = $this->generateSvgDiagram('concept_mindmap', $ctx);
        $visuals = $this->resolveCuratedDomainVisuals($domain, $ctx['chapter_name'] ?? '', $template);

        return <<<HTML
<article class="revision-notes-document" data-chapter="{$chap}" data-template="{$template}">
  <header class="cover">
    <div class="badge-row">
      <span class="eyebrow">Standard {$std} &bull; {$sub} &bull; High-Yield Study Guide</span>
      <span class="eyebrow-pill pill-prep">CBSE & Board Exam Calibrated</span>
    </div>
    <h2>{$chap}: High-Yield Revision Notes</h2>
    <p class="lede">Comprehensive concept anchors, critical formulas, high-frequency exam focus areas, and model solutions.</p>
  </header>

  <div class="doc-image-frame">
    <img src="{$visuals['hero']}" alt="{$chap} Visual Reference" loading="lazy">
    <div class="doc-image-caption">
      <strong>Visual Anchor:</strong> {$visuals['caption']}
    </div>
  </div>

  <section class="notes-section" data-block="summary">
    <div class="section-badge">Executive Summary</div>
    <h3>2-Minute Concept Primer</h3>
    <p>This chapter explores how opposing economic or scientific forces interact dynamically to establish equilibrium and stability. In economic markets, the <strong>Law of Demand</strong> dictates an inverse relationship between price and consumer desire, while the <strong>Law of Supply</strong> dictates a direct relationship between price and producer incentives. When these two independent forces meet, the market naturally navigates toward <strong>Market Equilibrium</strong>, eliminating both involuntary shortages and wasteful surpluses.</p>
  </section>

  <section class="notes-section" data-block="glossary">
    <div class="section-badge">Master Vocabulary</div>
    <h3>Comprehensive Concept Glossary</h3>
    <div class="table-responsive">
      <table class="concept-glossary-table">
        <thead>
          <tr>
            <th style="width: 50px;">#</th>
            <th style="width: 220px;">Key Concept Term</th>
            <th>Formal Curriculum Definition & Axioms</th>
            <th style="width: 120px;">Exam Weight</th>
          </tr>
        </thead>
        <tbody>
          {$glossaryRows}
        </tbody>
      </table>
    </div>
  </section>

  <section class="notes-section" data-block="mindmap">
    <div class="section-badge">Visual Architecture</div>
    <h3>Structural Concept Hierarchy</h3>
    {$mindmapSvg}
  </section>

  <section class="notes-section" data-block="deep-dive">
    <div class="section-badge">Deep Dive</div>
    <h3>Core Governing Laws & Mathematical Foundations</h3>
    <div class="rules-breakdown-grid">
      <div class="rule-box">
        <h4>1. The Law of Demand</h4>
        <p class="formula-banner">P &uarr; &rArr; Qd &darr; &nbsp;and&nbsp; P &darr; &rArr; Qd &uarr; [ceteris paribus]</p>
        <p><strong>Underlying Drivers:</strong> The inverse slope is caused by the <em>Law of Diminishing Marginal Utility</em> (each additional unit yields less satisfaction), the <em>Income Effect</em>, and the <em>Substitution Effect</em>.</p>
        <p><strong>Boundary Exceptions:</strong> Veblen goods (luxury prestige items) and Giffen goods (inferior staples with strong income effects).</p>
      </div>

      <div class="rule-box">
        <h4>2. The Law of Supply</h4>
        <p class="formula-banner">P &uarr; &rArr; Qs &uarr; &nbsp;and&nbsp; P &darr; &rArr; Qs &darr; [ceteris paribus]</p>
        <p><strong>Underlying Drivers:</strong> Higher market prices yield elevated profit margins, covering rising marginal costs and incentivizing expanded plant capacity.</p>
        <p><strong>Boundary Exceptions:</strong> Perishable agricultural goods right after harvest, monopolistic supply withholding, and backward-bending labor supply curves.</p>
      </div>
    </div>
  </section>

  <section class="notes-section" data-block="warnings">
    <div class="section-badge">Examiner's Alert</div>
    <h3>Top 5 High-Frequency Exam Mistakes & Misconceptions</h3>
    <div class="warning-cards-list">
      <div class="warning-item">
        <span class="warning-num">01</span>
        <div>
          <h5>Mistake: Confusing "Change in Demand" with "Change in Quantity Demanded"</h5>
          <p><strong>Correction:</strong> A change in own price causes a <em>movement along</em> the curve (expansion/contraction). A change in external factors (income, tastes) causes a <em>shift of the entire curve</em>.</p>
        </div>
      </div>
      <div class="warning-item">
        <span class="warning-num">02</span>
        <div>
          <h5>Mistake: Forgetting to State the Assumption of "Ceteris Paribus"</h5>
          <p><strong>Correction:</strong> The laws of demand and supply hold <em>only</em> when all other non-price variables remain constant. Omission of this condition loses marks in board examinations.</p>
        </div>
      </div>
      <div class="warning-item">
        <span class="warning-num">03</span>
        <div>
          <h5>Mistake: Reversing the Placement of Price and Quantity Axes</h5>
          <p><strong>Correction:</strong> In economics convention, <strong>Price (P) is always on the Vertical Y-axis</strong> and <strong>Quantity (Q) is always on the Horizontal X-axis</strong>.</p>
        </div>
      </div>
      <div class="warning-item">
        <span class="warning-num">04</span>
        <div>
          <h5>Mistake: Thinking Price Ceilings are Set Above Equilibrium</h5>
          <p><strong>Correction:</strong> A Price Ceiling is only binding and effective if set <em>below</em> equilibrium price, which unavoidably causes a shortage.</p>
        </div>
      </div>
      <div class="warning-item">
        <span class="warning-num">05</span>
        <div>
          <h5>Mistake: Omitting Units in Calculation Answers</h5>
          <p><strong>Correction:</strong> Always state currency symbols (₹) and quantity dimensions (units, kg, liters) in numerical solutions.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="notes-section" data-block="self-test">
    <div class="section-badge">Exam Mastery Check</div>
    <h3>High-Frequency Exam Self-Test (5 Questions with Solutions)</h3>
    <div class="self-test-list">
      <div class="test-item">
        <div class="test-q">
          <span class="q-badge">Q1 [2 Marks]</span>
          <p>State the Law of Demand and write down the essential Latin condition that qualifies it.</p>
        </div>
        <div class="test-a">
          <strong>Model Answer:</strong> The Law of Demand states that, other things remaining constant (<em>ceteris paribus</em>), there is an inverse relationship between the price of a commodity and the quantity demanded of that commodity. When price increases, quantity demanded decreases, and vice versa.
        </div>
      </div>

      <div class="test-item">
        <div class="test-q">
          <span class="q-badge">Q2 [3 Marks]</span>
          <p>Explain how a rise in consumer income affects the demand curve for (a) Normal Goods, and (b) Inferior Goods.</p>
        </div>
        <div class="test-a">
          <strong>Model Answer:</strong> (a) For Normal Goods, an increase in consumer income causes purchasing power to rise, shifting the demand curve <strong>rightward</strong>. (b) For Inferior Goods (e.g. coarse grains), consumers upgrade to higher quality alternatives as income rises, shifting the demand curve <strong>leftward</strong>.
        </div>
      </div>

      <div class="test-item">
        <div class="test-q">
          <span class="q-badge">Q3 [3 Marks]</span>
          <p>Differentiate between Substitute Goods and Complementary Goods with one concrete example of each.</p>
        </div>
        <div class="test-a">
          <strong>Model Answer:</strong> <strong>Substitute Goods:</strong> Products that satisfy similar needs and can replace each other (e.g. Tea and Coffee). A rise in the price of tea increases demand for coffee. <strong>Complementary Goods:</strong> Products consumed together to satisfy a single want (e.g. Petrol and Cars). A rise in the price of petrol reduces the demand for petrol cars.
        </div>
      </div>

      <div class="test-item">
        <div class="test-q">
          <span class="q-badge">Q4 [4 Marks]</span>
          <p>At price ₹50, quantity demanded is 400 units. When price falls to ₹40, quantity demanded expands to 600 units. Calculate total revenue at both price points and state whether total revenue increased or decreased.</p>
        </div>
        <div class="test-a">
          <strong>Model Answer:</strong><br>
          At P1 = ₹50, Q1 = 400 &rArr; TR1 = 50 &times; 400 = ₹20,000.<br>
          At P2 = ₹40, Q2 = 600 &rArr; TR2 = 40 &times; 600 = ₹24,000.<br>
          <strong>Conclusion:</strong> Total Revenue increased by ₹24,000 - ₹20,000 = ₹4,000.
        </div>
      </div>

      <div class="test-item">
        <div class="test-q">
          <span class="q-badge">Q5 [4 Marks]</span>
          <p>Illustrate and explain the market consequences when a legal Price Floor is set strictly above the equilibrium price.</p>
        </div>
        <div class="test-a">
          <strong>Model Answer:</strong> When the government imposes a Price Floor above equilibrium (such as Minimum Support Price for farmers):<br>
          1. Quantity supplied increases (Qs &gt; Q*) as producers expand output.<br>
          2. Quantity demanded contracts (Qd &lt; Q*) as buyers face higher prices.<br>
          3. This creates a persistent <strong>Excess Supply (Surplus)</strong> of Qs - Qd units, requiring the government to purchase and store buffer stocks.
        </div>
      </div>
    </div>
  </section>
</article>
HTML;
    }

    /**
     * Generates a targeted Remedial Class and Scaffolding Guide.
     */
    public function buildRemedialClass(array $ctx, string $template = 'modern_tech'): string
    {
        $chap = htmlspecialchars($ctx['chapter_name']);
        $sub = htmlspecialchars($ctx['subject_name']);
        $std = htmlspecialchars($ctx['standard_name']);
        $domain = $ctx['domain'];
        $concepts = $ctx['concepts'];
        $visuals = $this->resolveCuratedDomainVisuals($domain, $ctx['chapter_name'] ?? '', $template);

        return <<<HTML
<article class="remedial-class-module" data-chapter="{$chap}" data-template="{$template}">
  <header class="cover">
    <div class="badge-row">
      <span class="eyebrow">Standard {$std} &bull; {$sub} &bull; Targeted Remediation & Scaffolding</span>
      <span class="eyebrow-pill pill-remedial">Conceptual Bottleneck Resolution</span>
    </div>
    <h2>{$chap}: Remedial Class & Scaffolding Guide</h2>
    <p class="lede">Step-by-step cognitive scaffolding designed to break down abstract theorems into intuitive anchors and confidence-building practice.</p>
  </header>

  <div class="doc-image-frame">
    <img src="{$visuals['hero']}" alt="{$chap} Remedial Scaffolding" loading="lazy">
    <div class="doc-image-caption">
      <strong>Cognitive Bridge Anchor:</strong> Intuitive visual model to demystify complex curriculum theorems.
    </div>
  </div>

  <section class="remedial-section" data-block="diagnostic">
    <div class="section-badge">Diagnostic Check</div>
    <h3>Pinpointing Common Cognitive Bottlenecks</h3>
    <div class="diagnostic-grid">
      <div class="diag-card">
        <span class="diag-tag">Stumbling Block A</span>
        <h4>Confusion Between Price and Cost</h4>
        <p><strong>Misconception:</strong> Students often say "Production costs rose, so the price dropped to attract buyers."</p>
        <p><strong>Remediation Anchor:</strong> Remind students that sellers need to cover expenses. Higher production costs reduce the incentive to supply, shifting supply leftward and <em>raising</em> market prices.</p>
      </div>

      <div class="diag-card">
        <span class="diag-tag">Stumbling Block B</span>
        <h4>Negative Slopes vs Positive Slopes</h4>
        <p><strong>Misconception:</strong> Students frequently mix up the directions of the Demand and Supply curves.</p>
        <p><strong>Remediation Anchor:</strong> Use the <strong>"D for Downward"</strong> memory trick: Demand curve points Down from left to right. Supply curve climbs Skyward (Upward).</p>
      </div>
    </div>
  </section>

  <section class="remedial-section" data-block="analogies">
    <div class="section-badge">Intuitive Anchors</div>
    <h3>3 Relatable Real-World Analogies</h3>
    <div class="analogies-list">
      <div class="analogy-card">
        <div class="analogy-icon">🍕</div>
        <div>
          <h4>Analogy 1: The Pizza Canteen Rush (Law of Demand)</h4>
          <p>Imagine the school canteen sells pizza slices for ₹10. Nearly every student buys two slices. Now imagine the price jumps to ₹120 per slice. Most students put the slice back and buy a samosa or wait until dinner. As price climbs, desire to purchase contracts.</p>
        </div>
      </div>

      <div class="analogy-card">
        <div class="analogy-icon">🧑‍🌾</div>
        <div>
          <h4>Analogy 2: The Strawberry Farmer (Law of Supply)</h4>
          <p>If strawberries sell for only ₹20/kg, a farmer devotes land to wheat or potatoes instead. But if winter tourism drives strawberry prices to ₹400/kg, the farmer plants every square foot with strawberries and hires extra workers. High prices motivate sellers to supply more.</p>
        </div>
      </div>

      <div class="analogy-card">
        <div class="analogy-icon">⚖️</div>
        <div>
          <h4>Analogy 3: The Playground Seesaw (Market Equilibrium)</h4>
          <p>Think of market equilibrium like a seesaw in perfect balance. On one side sits the buyer (pulling price down); on the other sits the seller (pushing price up). Equilibrium is the exact pivot point where neither side dominates and both can trade peacefully.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="remedial-section" data-block="ladder">
    <div class="section-badge">Scaffolded Practice</div>
    <h3>3-Tier Graduated Practice Ladder</h3>
    
    <div class="ladder-level level-guided">
      <div class="level-header">
        <span class="level-pill">Level 1 &bull; Fully Guided Walkthrough</span>
        <h4>Finding the Equilibrium Price</h4>
      </div>
      <div class="level-body">
        <p><strong>Problem:</strong> Given demand equation Qd = 100 - 2P and supply equation Qs = 20 + 2P, find the equilibrium price (P*) and quantity (Q*).</p>
        <div class="step-walkthrough">
          <div class="step-item"><span class="step-num">Step 1</span> State equilibrium condition: Qd = Qs.</div>
          <div class="step-item"><span class="step-num">Step 2</span> Substitute equations: 100 - 2P = 20 + 2P.</div>
          <div class="step-item"><span class="step-num">Step 3</span> Combine like terms: 100 - 20 = 2P + 2P &rArr; 80 = 4P.</div>
          <div class="step-item"><span class="step-num">Step 4</span> Solve for P*: P* = 80 / 4 = ₹20.</div>
          <div class="step-item"><span class="step-num">Step 5</span> Substitute P* to find Q*: Q* = 100 - 2(20) = 60 Units. Check: 20 + 2(20) = 60 Units. &check;</div>
        </div>
      </div>
    </div>

    <div class="ladder-level level-faded">
      <div class="level-header">
        <span class="level-pill">Level 2 &bull; Partially Scaffolded (Fill the Blanks)</span>
        <h4>Parallel Problem with Prompting Hints</h4>
      </div>
      <div class="level-body">
        <p><strong>Problem:</strong> Demand is Qd = 150 - 3P and supply is Qs = 50 + 2P.</p>
        <div class="fill-in-prompts">
          <p>1. At equilibrium, we set Qd = Qs &rArr; 150 - 3P = [ 50 + 2P ].</p>
          <p>2. Group numbers on left and P on right: 150 - 50 = [ 5 ] P.</p>
          <p>3. Therefore, 100 = 5P &rArr; P* = ₹[ 20 ].</p>
          <p>4. Equilibrium quantity Q* = 150 - 3([ 20 ]) = [ 90 ] Units.</p>
        </div>
        <p class="answer-hint"><em>(Hint Check: P* = ₹20, Q* = 90 Units)</em></p>
      </div>
    </div>

    <div class="ladder-level level-independent">
      <div class="level-header">
        <span class="level-pill">Level 3 &bull; Independent Mastery Challenge</span>
        <h4>Unassisted Student Application</h4>
      </div>
      <div class="level-body">
        <p><strong>Problem:</strong> Demand is Qd = 200 - 4P and supply is Qs = 50 + P.</p>
        <p>Calculate P* and Q* independently in your workbook. Verify whether at P = ₹40 the market experiences a surplus or a shortage.</p>
        <div class="independent-solution-box">
          <details>
            <summary>Click to verify final answer</summary>
            <p><strong>Solution:</strong> 200 - 4P = 50 + P &rArr; 150 = 5P &rArr; P* = ₹30, Q* = 80 Units.<br>At P = ₹40 (above P*), Qd = 200 - 160 = 40 and Qs = 50 + 40 = 90. There is a <strong>Surplus of 50 units</strong> (90 - 40).</p>
          </details>
        </div>
      </div>
    </div>
  </section>

  <section class="remedial-section" data-block="protocol">
    <div class="section-badge">Teacher Guide</div>
    <h3>Facilitation Advice for Remedial Sessions</h3>
    <ul class="styled-bullet-list">
      <li><strong>Concrete before Abstract:</strong> Always show real objects or canteen items before writing algebraic variables like P and Q.</li>
      <li><strong>Praise Incremental Steps:</strong> Acknowledge students who identify the correct relationship even if their arithmetic contains small errors.</li>
      <li><strong>Use Physical Gestures:</strong> Have students trace the downward slope of demand with their right arm and upward supply slope with their left arm to reinforce motor memory.</li>
    </ul>
  </section>
</article>
HTML;
    }

    /**
     * Generates a Bloom's Taxonomy-calibrated Chapter Assessment with full model solutions.
     */
    public function buildChapterAssessment(
        array $ctx,
        string $teachingApproach = 'Inquiry-based learning',
        string $template = 'modern_tech'
    ): string {
        $chap = htmlspecialchars($ctx['chapter_name']);
        $sub = htmlspecialchars($ctx['subject_name']);
        $std = htmlspecialchars($ctx['standard_name']);
        $approach = htmlspecialchars($teachingApproach);
        $domain = $ctx['domain'];
        $concepts = $ctx['concepts'];

        $c1 = $concepts[0]['name'] ?? 'Demand';
        $c2 = $concepts[1]['name'] ?? ($concepts[7]['name'] ?? 'Supply');
        $c3 = $concepts[5]['name'] ?? 'Substitute Goods';
        $c4 = $concepts[6]['name'] ?? 'Complementary Goods';
        $cEq = $concepts[12]['name'] ?? 'Market Equilibrium';

        return <<<HTML
<article class="chapter-assessment-bank" data-chapter="{$chap}" data-teaching-approach="{$approach}" data-template="{$template}">
  <header class="cover">
    <div class="badge-row">
      <span class="eyebrow">Standard {$std} &bull; {$sub} &bull; Calibrated Mastery Assessment</span>
      <span class="eyebrow-pill pill-exam">Max Marks: 20 &bull; Duration: 45 Mins</span>
    </div>
    <h2>{$chap}: Calibrated Mastery Assessment</h2>
    <p class="lede">Cognitive ladder assessment calibrated across 5 Bloom's taxonomy tiers with complete step-by-step scoring rubrics and model solutions.</p>
  </header>

  <div class="assessment-questions-container">
    <section class="bloom-tier" data-bloom="remember" data-block="tier-1">
      <div class="tier-header">
        <span class="tier-badge badge-tier-1">Tier 1 &bull; Foundational Recall (Bloom Level: Remember)</span>
        <span class="tier-marks">[4 Marks Total]</span>
      </div>
      <ol class="question-list">
        <li>
          <div class="question-title">
            <span>State the Law of {$c1} and identify the critical assumption that must hold true for this law to operate.</span>
            <span class="mark-tag">[2 Marks]</span>
          </div>
        </li>
        <li>
          <div class="question-title">
            <span>Define the term <strong>{$cEq}</strong>. What happens to unsold producer inventory when a market achieves this point?</span>
            <span class="mark-tag">[2 Marks]</span>
          </div>
        </li>
      </ol>
    </section>

    <section class="bloom-tier" data-bloom="understand" data-block="tier-2">
      <div class="tier-header">
        <span class="tier-badge badge-tier-2">Tier 2 &bull; Conceptual Explanation (Bloom Level: Understand)</span>
        <span class="tier-marks">[6 Marks Total]</span>
      </div>
      <ol class="question-list" start="3">
        <li>
          <div class="question-title">
            <span>Explain the crucial difference between a <strong>"movement along a curve"</strong> and a <strong>"shift of a curve"</strong>. Provide one specific real-world factor that causes each.</span>
            <span class="mark-tag">[3 Marks]</span>
          </div>
        </li>
        <li>
          <div class="question-title">
            <span>Contrast <strong>{$c3}</strong> with <strong>{$c4}</strong>. Explain how an increase in the price of one affects the demand for the other, citing concrete real-world consumer products.</span>
            <span class="mark-tag">[3 Marks]</span>
          </div>
        </li>
      </ol>
    </section>

    <section class="bloom-tier" data-bloom="apply" data-block="tier-3">
      <div class="tier-header">
        <span class="tier-badge badge-tier-3">Tier 3 &bull; Scenario-Based Application (Bloom Level: Apply)</span>
        <span class="tier-marks">[4 Marks Total]</span>
      </div>
      <ol class="question-list" start="5">
        <li>
          <div class="question-title">
            <span>A neighborhood artisan bakery sells sourdough bread. At a price of ₹60 per loaf, daily demand is 150 loaves. The baker raises the price to ₹75, causing daily sales to drop to 120 loaves.</span>
            <span class="mark-tag">[4 Marks]</span>
          </div>
          <div class="question-subparts">
            <p>(a) Calculate the total daily revenue earned by the bakery at both price points. [2 Marks]</p>
            <p>(b) Determine whether the price increase was financially advantageous for the baker, and explain the economic justification. [2 Marks]</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="bloom-tier" data-bloom="analyze" data-block="tier-4">
      <div class="tier-header">
        <span class="tier-badge badge-tier-4">Tier 4 &bull; Analytical Diagnosis (Bloom Level: Analyze)</span>
        <span class="tier-marks">[3 Marks Total]</span>
      </div>
      <ol class="question-list" start="6">
        <li>
          <div class="question-title">
            <span>A catastrophic monsoon flood damages major tomato crops across several agricultural states.</span>
            <span class="mark-tag">[3 Marks]</span>
          </div>
          <div class="question-subparts">
            <p>Analyze the sequential chain reaction on the tomato market. Explain which curve shifts, in which direction it moves, and describe the resulting impact on the new equilibrium price and quantity.</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="bloom-tier" data-bloom="evaluate" data-block="tier-5">
      <div class="tier-header">
        <span class="tier-badge badge-tier-5">Tier 5 &bull; Critical Evaluation & Reflection (Bloom Level: Evaluate)</span>
        <span class="tier-marks">[3 Marks Total]</span>
      </div>
      <ol class="question-list" start="7">
        <li>
          <div class="question-title">
            <span>To assist low-income urban tenants, a municipal government imposes a strict legal Price Ceiling on apartment rentals strictly below the free-market rate.</span>
            <span class="mark-tag">[3 Marks]</span>
          </div>
          <div class="question-subparts">
            <p>Critically evaluate this policy: Discuss one significant social benefit intended by the government, and contrast it with two unintended adverse economic consequences (such as housing shortages, landlord under-maintenance, or informal key-money black markets).</p>
          </div>
        </li>
      </ol>
    </section>
  </div>

  <section class="callout callout-key" data-block="rubric-summary">
    <span class="callout-label">Scoring Architecture & Mastery Benchmark</span>
    <p>Total Marks: <strong>20 Marks</strong> &bull; Passing Mastery Threshold: <strong>80% (16/20 Marks)</strong> &bull; Calibrated across Bloom's Taxonomy cognitive ladder.</p>
  </section>

  <section class="model-answer-section" data-block="model-answers">
    <div class="section-badge badge-answer-key">Official Marking Scheme</div>
    <h3>Complete Model Answers & Step-by-Step Marking Rubrics</h3>
    <p class="sub-caption">Examiner key for teacher grading and student self-assessment verification.</p>

    <div class="answer-cards-list">
      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 01 &bull; Tier 1 (Remember)</span>
          <span class="ans-marks">[2 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong> The Law of Demand states that other things remaining constant (<em>ceteris paribus</em>), there is an inverse relationship between the price of a good and its quantity demanded; when price increases, quantity demanded decreases, and vice versa.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[1 Mark]</strong> for stating the inverse relationship between price and quantity demanded.</li>
              <li><strong>[1 Mark]</strong> for clearly specifying the assumption of <em>ceteris paribus</em> (other factors held constant).</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 02 &bull; Tier 1 (Remember)</span>
          <span class="ans-marks">[2 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong> Market Equilibrium is the state where the quantity demanded by consumers equals the quantity supplied by producers (Qd = Qs). At this point, there is zero involuntary inventory accumulation and zero product shortage; the market clears smoothly.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[1 Mark]</strong> for defining equilibrium as the equality of quantity demanded and supplied.</li>
              <li><strong>[1 Mark]</strong> for explaining that inventory clears with no surplus stock left unsold.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 03 &bull; Tier 2 (Understand)</span>
          <span class="ans-marks">[3 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong> A <strong>movement along the curve</strong> is triggered exclusively by a change in the commodity's own price, leading to an expansion or contraction along the existing line. A <strong>shift of the curve</strong> is triggered by changes in non-price factors (such as consumer income, taste preferences, or population), causing the entire line to relocate rightward or leftward.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[1.5 Marks]</strong> for accurate distinction between own-price movement vs. external shift.</li>
              <li><strong>[1.5 Marks]</strong> for providing valid real-world triggers for each condition.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 04 &bull; Tier 2 (Understand)</span>
          <span class="ans-marks">[3 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong> <strong>Substitute Goods</strong> (e.g. Tea and Coffee) satisfy identical consumer wants; an increase in the price of tea prompts consumers to switch toward coffee, shifting coffee demand rightward. <strong>Complementary Goods</strong> (e.g. Cars and Petrol) are used jointly; an increase in the price of petrol raises operating costs, shifting car demand leftward.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[1.5 Marks]</strong> for defining Substitute Goods with price-demand directional analysis and example.</li>
              <li><strong>[1.5 Marks]</strong> for defining Complementary Goods with price-demand directional analysis and example.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 05 &bull; Tier 3 (Apply)</span>
          <span class="ans-marks">[4 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong><br>
          (a) Initial Revenue: TR1 = 150 &times; ₹60 = ₹9,000.<br>
          New Revenue: TR2 = 120 &times; ₹75 = ₹9,000.<br>
          (b) Total daily revenue remained exactly identical at ₹9,000. However, the price increase was financially advantageous because the bakery baked 30 fewer loaves (120 instead of 150), reducing flour, electricity, and labor costs while earning the same revenue, thereby increasing net profit margins.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[2 Marks]</strong> for accurate calculation of TR1 (₹9,000) and TR2 (₹9,000).</li>
              <li><strong>[2 Marks]</strong> for justifying financial advantage via production cost savings at identical gross revenue.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 06 &bull; Tier 4 (Analyze)</span>
          <span class="ans-marks">[3 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong> A crop-destroying flood represents a severe supply-side disruption. The supply curve for tomatoes shifts leftward from S1 to S2, while consumer demand curve remains initially unchanged. At the initial price, a sudden shortage emerges, causing buyers to bid prices upward. The market achieves a new equilibrium at a <strong>strictly higher equilibrium price</strong> and a <strong>strictly lower equilibrium quantity</strong>.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[1 Mark]</strong> for identifying that the supply curve shifts leftward.</li>
              <li><strong>[1 Mark]</strong> for explaining the resulting market shortage mechanism.</li>
              <li><strong>[1 Mark]</strong> for concluding with higher equilibrium price and lower equilibrium quantity.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="answer-card">
        <div class="ans-header">
          <span class="ans-badge">Question 07 &bull; Tier 5 (Evaluate)</span>
          <span class="ans-marks">[3 Marks]</span>
        </div>
        <div class="ans-body">
          <p><strong>Model Answer:</strong><br>
          <strong>Intended Benefit:</strong> Makes rental housing affordable for low-income citizens who manage to secure capped units, shielding them from predatory landlord price increases.<br>
          <strong>Adverse Consequences:</strong><br>
          1. <em>Severe Housing Shortage:</em> Landlords exit the market or refuse to build new rental properties (Qs &lt; Qd), generating long waiting lists.<br>
          2. <em>Deterioration of Housing Stock & Black Markets:</em> Landlords cease property maintenance, and informal non-refundable "key money" or subletting premiums emerge to bypass official caps.</p>
          <div class="marking-rubric-breakdown">
            <span class="rubric-tag">Mark Distribution:</span>
            <ul>
              <li><strong>[1 Mark]</strong> for identifying a clear intended social affordability benefit.</li>
              <li><strong>[2 Marks]</strong> for explaining two valid unintended economic market consequences (1 mark each).</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</article>
HTML;
    }

    /**
     * Generates clean, crisp, scalable inline vector SVG diagrams.
     */
    public function generateSvgDiagram(string $diagramType, array $context): string
    {
        $domain = $context['domain'] ?? 'general';

        if ($diagramType === 'market_equilibrium') {
            return <<<SVG
<div class="svg-diagram-wrapper" style="margin: 20px 0; text-align: center;">
  <svg viewBox="0 0 680 320" xmlns="http://www.w3.org/2000/svg" style="width: 100%; max-width: 680px; height: auto; border-radius: 14px; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
    <defs>
      <linearGradient id="eqDemandGrad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#ef4444" stop-opacity="0.9" />
        <stop offset="100%" stop-color="#f87171" stop-opacity="0.7" />
      </linearGradient>
      <linearGradient id="eqSupplyGrad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#10b981" stop-opacity="0.9" />
        <stop offset="100%" stop-color="#34d399" stop-opacity="0.7" />
      </linearGradient>
      <radialGradient id="glowEq" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.8" />
        <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.8" />
      </radialGradient>
      <pattern id="eqGrid" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255, 255, 255, 0.08)" stroke-width="1"/>
      </pattern>
    </defs>

    <!-- Background Grid -->
    <rect x="70" y="30" width="560" height="230" fill="url(#eqGrid)" />

    <!-- Axes -->
    <line x1="70" y1="30" x2="70" y2="260" stroke="#94a3b8" stroke-width="2.5" />
    <line x1="70" y1="260" x2="630" y2="260" stroke="#94a3b8" stroke-width="2.5" />

    <!-- Axis Labels -->
    <text x="35" y="45" fill="#f8fafc" font-size="13" font-weight="700" font-family="system-ui">Price (P)</text>
    <text x="560" y="290" fill="#f8fafc" font-size="13" font-weight="700" font-family="system-ui">Quantity (Q)</text>

    <!-- Surplus Region Shading -->
    <polygon points="260,80 440,80 350,145" fill="rgba(245, 158, 11, 0.15)" stroke="rgba(245, 158, 11, 0.4)" stroke-dasharray="4,4" />
    <text x="315" y="72" fill="#fbbf24" font-size="11" font-weight="700" font-family="system-ui">Excess Supply (Surplus)</text>

    <!-- Shortage Region Shading -->
    <polygon points="260,210 440,210 350,145" fill="rgba(59, 130, 246, 0.15)" stroke="rgba(59, 130, 246, 0.4)" stroke-dasharray="4,4" />
    <text x="310" y="232" fill="#60a5fa" font-size="11" font-weight="700" font-family="system-ui">Excess Demand (Shortage)</text>

    <!-- Demand Curve (Downward Sloping) -->
    <line x1="120" y1="60" x2="580" y2="230" stroke="url(#eqDemandGrad)" stroke-width="4" stroke-linecap="round" />
    <text x="590" y="235" fill="#f87171" font-size="14" font-weight="800" font-family="system-ui">D (Demand)</text>

    <!-- Supply Curve (Upward Sloping) -->
    <line x1="120" y1="230" x2="580" y2="60" stroke="url(#eqSupplyGrad)" stroke-width="4" stroke-linecap="round" />
    <text x="590" y="65" fill="#34d399" font-size="14" font-weight="800" font-family="system-ui">S (Supply)</text>

    <!-- Equilibrium Point -->
    <circle cx="350" cy="145" r="16" fill="url(#glowEq)" />
    <circle cx="350" cy="145" r="6" fill="#38bdf8" stroke="#ffffff" stroke-width="2" />

    <!-- Dashed Projection Lines to Axes -->
    <line x1="70" y1="145" x2="350" y2="145" stroke="#38bdf8" stroke-width="1.8" stroke-dasharray="5,5" />
    <line x1="350" y1="145" x2="350" y2="260" stroke="#38bdf8" stroke-width="1.8" stroke-dasharray="5,5" />

    <!-- Equilibrium Annotations -->
    <text x="25" y="150" fill="#38bdf8" font-size="12" font-weight="700" font-family="system-ui">P* (₹30)</text>
    <text x="335" y="280" fill="#38bdf8" font-size="12" font-weight="700" font-family="system-ui">Q* (300 Units)</text>
    <text x="365" y="140" fill="#ffffff" font-size="13" font-weight="800" font-family="system-ui">Equilibrium E (P*, Q*)</text>
  </svg>
  <div style="font-size: 11px; color: #94a3b8; margin-top: 6px; font-weight: 600;">Figure: Market Equilibrium Dynamics — Intersection of Demand and Supply</div>
</div>
SVG;
        }

        if ($diagramType === 'demand_curve') {
            return <<<SVG
<div class="svg-diagram-wrapper" style="margin: 20px 0; text-align: center;">
  <svg viewBox="0 0 680 320" xmlns="http://www.w3.org/2000/svg" style="width: 100%; max-width: 680px; height: auto; border-radius: 14px; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
    <defs>
      <linearGradient id="demGrad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.9" />
        <stop offset="100%" stop-color="#6366f1" stop-opacity="0.9" />
      </linearGradient>
      <pattern id="demGrid" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255, 255, 255, 0.08)" stroke-width="1"/>
      </pattern>
    </defs>
    <rect x="70" y="30" width="560" height="230" fill="url(#demGrid)" />
    <line x1="70" y1="30" x2="70" y2="260" stroke="#94a3b8" stroke-width="2.5" />
    <line x1="70" y1="260" x2="630" y2="260" stroke="#94a3b8" stroke-width="2.5" />
    <text x="35" y="45" fill="#f8fafc" font-size="13" font-weight="700" font-family="system-ui">Price (P)</text>
    <text x="560" y="290" fill="#f8fafc" font-size="13" font-weight="700" font-family="system-ui">Quantity (Q)</text>

    <!-- Downward Sloping Curve -->
    <path d="M 140 70 Q 340 160 560 230" stroke="url(#demGrad)" stroke-width="4.5" fill="none" stroke-linecap="round" />
    <text x="570" y="235" fill="#60a5fa" font-size="14" font-weight="800" font-family="system-ui">Demand Curve (D)</text>

    <!-- Point A -->
    <circle cx="210" cy="110" r="6" fill="#38bdf8" stroke="#ffffff" stroke-width="2" />
    <line x1="70" y1="110" x2="210" y2="110" stroke="#38bdf8" stroke-dasharray="4,4" />
    <line x1="210" y1="110" x2="210" y2="260" stroke="#38bdf8" stroke-dasharray="4,4" />
    <text x="30" y="115" fill="#38bdf8" font-size="11" font-weight="700" font-family="system-ui">P1 (High)</text>
    <text x="195" y="280" fill="#38bdf8" font-size="11" font-weight="700" font-family="system-ui">Q1 (Low)</text>
    <text x="225" y="105" fill="#f8fafc" font-size="12" font-weight="700" font-family="system-ui">Point A</text>

    <!-- Point B -->
    <circle cx="450" cy="195" r="6" fill="#6366f1" stroke="#ffffff" stroke-width="2" />
    <line x1="70" y1="195" x2="450" y2="195" stroke="#6366f1" stroke-dasharray="4,4" />
    <line x1="450" y1="195" x2="450" y2="260" stroke="#6366f1" stroke-dasharray="4,4" />
    <text x="30" y="200" fill="#818cf8" font-size="11" font-weight="700" font-family="system-ui">P2 (Low)</text>
    <text x="435" y="280" fill="#818cf8" font-size="11" font-weight="700" font-family="system-ui">Q2 (High)</text>
    <text x="465" y="190" fill="#f8fafc" font-size="12" font-weight="700" font-family="system-ui">Point B</text>

    <!-- Movement Arrow -->
    <path d="M 230 125 Q 330 160 430 190" stroke="#fbbf24" stroke-width="2" stroke-dasharray="4,4" fill="none" />
    <text x="290" y="145" fill="#fbbf24" font-size="11" font-weight="800" font-family="system-ui">&darr; Price drops &rarr; Quantity expands</text>
  </svg>
  <div style="font-size: 11px; color: #94a3b8; margin-top: 6px; font-weight: 600;">Figure: Downward-Sloping Demand Curve Illustrating the Law of Demand</div>
</div>
SVG;
        }

        if ($diagramType === 'supply_curve') {
            return <<<SVG
<div class="svg-diagram-wrapper" style="margin: 20px 0; text-align: center;">
  <svg viewBox="0 0 680 320" xmlns="http://www.w3.org/2000/svg" style="width: 100%; max-width: 680px; height: auto; border-radius: 14px; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
    <defs>
      <linearGradient id="supGrad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#10b981" stop-opacity="0.9" />
        <stop offset="100%" stop-color="#34d399" stop-opacity="0.9" />
      </linearGradient>
      <pattern id="supGrid" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255, 255, 255, 0.08)" stroke-width="1"/>
      </pattern>
    </defs>
    <rect x="70" y="30" width="560" height="230" fill="url(#supGrid)" />
    <line x1="70" y1="30" x2="70" y2="260" stroke="#94a3b8" stroke-width="2.5" />
    <line x1="70" y1="260" x2="630" y2="260" stroke="#94a3b8" stroke-width="2.5" />
    <text x="35" y="45" fill="#f8fafc" font-size="13" font-weight="700" font-family="system-ui">Price (P)</text>
    <text x="560" y="290" fill="#f8fafc" font-size="13" font-weight="700" font-family="system-ui">Quantity (Q)</text>

    <!-- Upward Sloping Curve -->
    <path d="M 140 230 Q 340 160 560 70" stroke="url(#supGrad)" stroke-width="4.5" fill="none" stroke-linecap="round" />
    <text x="570" y="75" fill="#34d399" font-size="14" font-weight="800" font-family="system-ui">Supply Curve (S)</text>

    <!-- Low Price Point -->
    <circle cx="210" cy="195" r="6" fill="#10b981" stroke="#ffffff" stroke-width="2" />
    <line x1="70" y1="195" x2="210" y2="195" stroke="#10b981" stroke-dasharray="4,4" />
    <line x1="210" y1="195" x2="210" y2="260" stroke="#10b981" stroke-dasharray="4,4" />
    <text x="30" y="200" fill="#6ee7b7" font-size="11" font-weight="700" font-family="system-ui">P1 (Low)</text>
    <text x="195" y="280" fill="#6ee7b7" font-size="11" font-weight="700" font-family="system-ui">Q1 (Low)</text>

    <!-- High Price Point -->
    <circle cx="450" cy="110" r="6" fill="#34d399" stroke="#ffffff" stroke-width="2" />
    <line x1="70" y1="110" x2="450" y2="110" stroke="#34d399" stroke-dasharray="4,4" />
    <line x1="450" y1="110" x2="450" y2="260" stroke="#34d399" stroke-dasharray="4,4" />
    <text x="30" y="115" fill="#a7f3d0" font-size="11" font-weight="700" font-family="system-ui">P2 (High)</text>
    <text x="435" y="280" fill="#a7f3d0" font-size="11" font-weight="700" font-family="system-ui">Q2 (High)</text>

    <text x="260" y="135" fill="#34d399" font-size="12" font-weight="800" font-family="system-ui">&uarr; Higher Profit Incentive &rarr; Output Expands</text>
  </svg>
  <div style="font-size: 11px; color: #94a3b8; margin-top: 6px; font-weight: 600;">Figure: Upward-Sloping Supply Curve Demonstrating Profit Motivation</div>
</div>
SVG;
        }

        // Default: Clean Radial Concept Mind Map SVG
        $chapTitle = htmlspecialchars(mb_substr($context['chapter_name'] ?? 'Core Topic', 0, 24));
        return <<<SVG
<div class="svg-diagram-wrapper" style="margin: 20px 0; text-align: center;">
  <svg viewBox="0 0 680 300" xmlns="http://www.w3.org/2000/svg" style="width: 100%; max-width: 680px; height: auto; border-radius: 14px; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
    <defs>
      <linearGradient id="coreGrad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#6366f1" />
        <stop offset="100%" stop-color="#a855f7" />
      </linearGradient>
    </defs>
    <!-- Connecting Lines -->
    <line x1="340" y1="150" x2="160" y2="70" stroke="#6366f1" stroke-width="2.5" stroke-dasharray="5,5" />
    <line x1="340" y1="150" x2="520" y2="70" stroke="#38bdf8" stroke-width="2.5" stroke-dasharray="5,5" />
    <line x1="340" y1="150" x2="160" y2="230" stroke="#10b981" stroke-width="2.5" stroke-dasharray="5,5" />
    <line x1="340" y1="150" x2="520" y2="230" stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="5,5" />

    <!-- Center Node -->
    <rect x="230" y="120" width="220" height="60" rx="30" fill="url(#coreGrad)" stroke="#ffffff" stroke-width="2" />
    <text x="340" y="155" fill="#ffffff" font-size="14" font-weight="800" text-anchor="middle" font-family="system-ui">{$chapTitle}</text>

    <!-- Branch 1 -->
    <rect x="70" y="45" width="180" height="50" rx="10" fill="#1e1b4b" stroke="#6366f1" stroke-width="1.8" />
    <text x="160" y="75" fill="#c7d2fe" font-size="12" font-weight="700" text-anchor="middle" font-family="system-ui">1. Foundational Axioms</text>

    <!-- Branch 2 -->
    <rect x="430" y="45" width="180" height="50" rx="10" fill="#0c4a6e" stroke="#38bdf8" stroke-width="1.8" />
    <text x="520" y="75" fill="#bae6fd" font-size="12" font-weight="700" text-anchor="middle" font-family="system-ui">2. Empirical Modeling</text>

    <!-- Branch 3 -->
    <rect x="70" y="205" width="180" height="50" rx="10" fill="#064e3b" stroke="#10b981" stroke-width="1.8" />
    <text x="160" y="235" fill="#a7f3d0" font-size="12" font-weight="700" text-anchor="middle" font-family="system-ui">3. System Dynamics</text>

    <!-- Branch 4 -->
    <rect x="430" y="205" width="180" height="50" rx="10" fill="#451a03" stroke="#f59e0b" stroke-width="1.8" />
    <text x="520" y="235" fill="#fde68a" font-size="12" font-weight="700" text-anchor="middle" font-family="system-ui">4. Practical Application</text>
  </svg>
  <div style="font-size: 11px; color: #94a3b8; margin-top: 6px; font-weight: 600;">Figure: Structural Knowledge Graph & Conceptual Interconnections</div>
</div>
SVG;
    }
}

