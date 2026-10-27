# Learning Path / Roadmap UI Design Examples — Dribbble & Pinterest

Scope note on method: Dribbble and Pinterest pages are JS-rendered and could not be fetched directly for pixel-level verification (every WebFetch attempt against dribbble.com and pinterest.com URLs returned empty content). All descriptions below are built from the search engine's own result titles, tag/URL slugs, and the descriptive snippets the search tool returned for each query — not from a direct visual inspection of the rendered shot/pin. Every link listed is one that actually appeared in search results (titles quoted below are the exact titles search returned); none are fabricated. Where a description is thin because the snippet gave little visual detail, that is flagged explicitly rather than guessed.

## What distinct visual styles exist for "learning path" / "roadmap" / "journey map" / "progress path" UI on Dribbble and Pinterest?

### Takeaway
Across both platforms, search results cluster into seven recognizable visual styles: (1) Duolingo-style vertical winding node path, (2) flat-vector "learning path" journey illustrations for ed-tech products, (3) game level-select / world maps, (4) isometric 3D road & building maps, (5) vertical timeline/stepper roadmaps, (6) card-based connected roadmap flows (non-path, grid/kanban style), and (7) Pinterest curated mood-boards that mix several of the above. Dribbble surfaces individual polished shots per style; Pinterest mostly surfaces boards/"Ideas" hub pages that re-aggregate Dribbble shots plus generic infographic templates.

### Cited Findings
- A Duolingo-specific style cluster exists as its own Dribbble tag with many shots recreating or riffing on Duolingo's path UI — [Duolingo tag](https://dribbble.com/tags/duolingo); [Duolingo-style search](https://dribbble.com/search/duolingo-style)
- A general "learning path" product-UI style (dashboard + path combined, not gamified) exists, e.g. Platzi's student home redesign — [New home for students at Platzi – Part 1: Learning Paths](https://dribbble.com/shots/7561985-New-home-for-students-at-Platzi-Part-1-Learning-Paths)
- A game level-select/world-map style exists as its own tag and search term distinct from "learning path," borrowed from game UI design — [Game Map UI tag](https://dribbble.com/tags/game%20map%20ui); [Level Map search](https://dribbble.com/search/level-map)
- An isometric 3D road/building style exists, both as educational illustration and as a roadmap layout — [Learning – line isometric illustration](https://dribbble.com/shots/13374331-Learning-line-isometric-illustration); [Roadmap by NickZag](https://dribbble.com/shots/4366807-Roadmap)
- A vertical timeline/stepper style (numbered circles + connecting line, not a winding path) is common for onboarding and process-roadmap UI — [Steps / Roadmap Section – UI UX Redesign Process](https://dribbble.com/shots/23726304-Steps-Roadmap-Section-UI-UX-Redesign-Process)
- A card-based "roadmap" style exists but is largely a different genre (product-management Kanban roadmaps, not a path at all) — [Roadmap Feature Cards](https://dribbble.com/shots/14780471--Roadmap-Feature-Cards)
- Pinterest results for "learning path"/"roadmap UI" are noisy: many top hits are generic "how to learn UI/UX design" career roadmaps (infographic templates, not in-product path UI), e.g. [UX/UI Design learning roadmap](https://uk.pinterest.com/pin/935411785128514206/) and [120 Product Roadmap Templates, Examples & Ideas](https://ca.pinterest.com/venngage/product-roadmap-templates-examples-ideas/) — these are adjacent, not the gamified step-path style requested.

### Inferences
- Dribbble's tag/search system (`/tags/...`, `/search/...`) is a reliable way to keep browsing each style once a category is identified, since every style above has its own tag or search slug.
- "Roadmap UI" as a search term on both platforms skews toward product-management/business roadmaps (timelines, Kanban, Gantt) rather than gamified learner progress paths; "learning path," "journey map," "level map," and "Duolingo" are the higher-precision search terms for the K-12 gamified-path use case.

### Gaps
- Could not visually confirm exact color palettes, node shapes, or line-curve details by direct inspection (fetch tools returned no renderable content for any Dribbble/Pinterest URL); all style attributions rely on title text, URL slugs, and search-engine summaries.
- No reliable way to confirm shot popularity/recency (like counts, publish dates) without a working fetch — breadth was prioritized over verifying which shot is "most-liked" within a style.

## For each style found, what makes it visually distinct?

### Takeaway
The seven styles differ mainly on three axes: path geometry (winding S-curve vs. grid/cards vs. no path at all), dimensionality (flat vector vs. isometric/3D), and tone (playful/mascot-driven game UI vs. clean enterprise dashboard).

### Cited Findings
- **Duolingo-style winding path**: circular lesson "bubbles" strung along a serpentine vertical line, mascot characters, bright saturated color-blocking, playful 3D-ish icon treatment — described in [Duolingo mobile app redesign – 3d playful clean UI](https://dribbble.com/shots/16768109-Duolingo-mobile-app-redesign-3d-playful-clean-UI) and formalized in Duolingo's own [Design Guidelines](https://dribbble.com/shots/4029434-Duolingo-Design-Guidelines) by Jack Morgan for Duolingo.
- **Flat-vector learning-path journey illustration**: less game-like, more editorial — path is often a simple line/arc connecting course modules inside a dashboard rather than a full-screen game map, paired with progress stats and course cards, as in [Learning Path UI layouts](https://dribbble.com/shots/3594217-Learning-Path-UI-layouts) by Joel Rosen for Briefbox and [Mondly – UX/UI Dashboard Design for Language Learning Platform](https://dribbble.com/shots/23949154-Mondly-UX-UI-Dashboard-Design-for-Language-Learning-Platform).
- **Game level-select / world map**: a landscape or island-based map with level nodes scattered across terrain (hills, lakes, candy terrain, pirate islands), locked/greyed nodes vs. unlocked colorful ones, per the search summary for [Game level map by BLINK](https://dribbble.com/shots/6278889-Game-level-map) ("players starting in plains and hilly areas with natural elements like lakes, rivers, windmill camps, woods and hills, using a green color palette with gradual depth variations") and the candy-themed [Candy Game UI – Map / Stage / Level Select](https://dribbble.com/shots/24010815-Candy-Game-UI-Map-Stage-Level-Select).
- **Isometric 3D road/building map**: 3D-angled (isometric) roads, buildings, and terrain pieces assembled into a route or campus-like layout, distinct from the flat top-down game maps above — per [Learning – line isometric illustration](https://dribbble.com/shots/13374331-Learning-line-isometric-illustration) and the isometric-onboarding pin [Isometric onboarding screen by herdetya on Dribbble](https://www.pinterest.com/pin/isometric-onboarding-screen-by-herdetya-on-dribbble--471892867206398435/).
- **Vertical timeline/stepper**: numbered circular markers connected by a straight vertical line with a bordered icon per step, titles/descriptions beside each — no winding curve, more process-diagram than game map, per [Steps / Roadmap Section – UI UX Redesign Process](https://dribbble.com/shots/23726304-Steps-Roadmap-Section-UI-UX-Redesign-Process) and [User Journey Map for the App](https://dribbble.com/shots/16714187-User-Journey-Map-for-the-App) by Kate Pine.
- **Card-based connected flow**: feature/course content lives in discrete cards linked by connector lines or arranged in swimlanes, closer to a flowchart/Kanban board than a path metaphor, per [Roadmap Feature Cards](https://dribbble.com/shots/14780471--Roadmap-Feature-Cards) by Peter Pilat for Productboard and [Course Cards](https://dribbble.com/shots/6246423-Course-Cards) by Jamie Ritchie.

### Inferences
- The "winding path" visual metaphor the user wants most closely maps to the Duolingo-style and flat-vector learning-path categories; the isometric and game-level-map categories deliver the same locked/current/completed status-coloring but as a 3D or landscape metaphor rather than a simple ribbon; timeline/stepper and card-flow styles satisfy "sequential milestones" but drop the winding-road visual entirely, so they're weaker fits for a literal "road" look despite being common under the same search terms.
- For a K-12 gamified product specifically, the Duolingo-style and game-level-map styles are the most proven fits (both are already shipped, child-facing, gamified products), while isometric and card-flow styles are more common in B2B/SaaS or generic e-learning contexts.

### Gaps
- Snippets did not provide exact hex colors or typography for any shot; only qualitative color/shape descriptions were available (e.g., "green color palette," "vibrant colors") from search summaries.
- Could not determine the lock/current/completed color convention precisely for most shots beyond generic genre knowledge (greyed-out = locked, colorful/checkmark = complete) cited in the general game-level-select research snippet, not shot-specific data.

## Which specific shots/pins are the strongest, most polished examples of each style?

### Takeaway
Below is the curated shopping list: real, found URLs grouped by style, 2-4 per category, spanning both Dribbble (individual shots) and Pinterest (boards/pins, which mostly function as curated collections rather than single polished images).

### Cited Findings

**1. Duolingo-style vertical winding path (gamified lesson-node path)**
- [Make Duolingo UI your own](https://dribbble.com/shots/25616971-Make-Duolingo-UI-your-own) — Figr Design; a rebuildable recreation of Duolingo's actual winding node-path screen, explicitly framed as a customizable UI kit base.
- [Duolingo Mobile UI (Recreated)](https://dribbble.com/shots/22423503-Duolingo-Mobile-UI-Recreated) — Figr Design; a second, separate recreation of the Duolingo mobile path screens.
- [Duolingo mobile app redesign – 3d playful clean UI](https://dribbble.com/shots/16768109-Duolingo-mobile-app-redesign-3d-playful-clean-UI) — a redesign take explicitly tagged "3d playful clean UI," i.e. a more dimensional/glossy node-path treatment than the flat original.
- [Duolingo Design Guidelines](https://dribbble.com/shots/4029434-Duolingo-Design-Guidelines) — Jack Morgan for Duolingo (official); the design-system source shots that define the path's bubble/line/color conventions.
- [Duolingo Dashboard](https://dribbble.com/shots/15725497-Duolingo-Dashboard) — Juliana Camolese de Araujo; a dashboard-adjacent take on the same path concept.
- Pinterest boards for browsing more of this style: [35 ide DUOLINGO UI DESIGN](https://id.pinterest.com/lanang3dan/duolingo-ui-design/); [73 Duolingo Ui ideas](https://in.pinterest.com/imtndragon/duolingo-ui/); [Duolingo App – Learn Spanish, French and more](https://es.pinterest.com/pin/60517188736489003/).

**2. Flat-vector "learning path" journey illustrations (ed-tech dashboards, less game-like)**
- [New home for students at Platzi – Part 1: Learning Paths](https://dribbble.com/shots/7561985-New-home-for-students-at-Platzi-Part-1-Learning-Paths) — Uriel Ramirez; a real shipped ed-tech product's learning-path home screen redesign.
- [Learning Path UI layouts](https://dribbble.com/shots/3594217-Learning-Path-UI-layouts) — Joel Rosen for Briefbox; multiple layout variants for the same learning-path concept in one shot.
- [Mondly – UX/UI Dashboard Design for Language Learning Platform](https://dribbble.com/shots/23949154-Mondly-UX-UI-Dashboard-Design-for-Language-Learning-Platform) — Outcrowd; a polished dashboard treatment for a real language-learning brand.
- [Gamified Learning Dashboard – XP, Quests, Rewards UI](https://dribbble.com/shots/25971302-Gamified-Learning-Dashboard-XP-Quests-Rewards-UI) — combines a path/progress metaphor with XP, quest, and reward UI patterns.
- Browse more: [Learning Pathways tag](https://dribbble.com/tags/learning_pathways); [Learning Paths tag](https://dribbble.com/tags/learning_paths); [Path UI search](https://dribbble.com/search/path-ui); Pinterest [10 Best Learning Path images](https://id.pinterest.com/imeendurance/learning-path/) and [Learning Path Design](https://www.pinterest.com/ideas/learning-path-design/941962093876/).

**3. Game level-select / world maps (islands, terrain, locked/unlocked stages)**
- [Game level map](https://dribbble.com/shots/6278889-Game-level-map) — BLINK; green terrain map with plains/hills/lakes/windmill camps and depth-gradient coloring connecting level nodes.
- [Candy Game UI – Map / Stage / Level Select](https://dribbble.com/shots/24010815-Candy-Game-UI-Map-Stage-Level-Select) — Zarbab Ahmed; a candy-themed stage-select map, high color saturation, strong fit for a playful K-12 tone.
- [Game Level Map With 5 Different Worlds](https://dribbble.com/shots/3524199-Game-Level-Map-With-5-Different-Worlds) — wowu; multi-world map structure useful if the curriculum needs grouped chapter "worlds."
- [Mobile Game – Map UI Animation](https://dribbble.com/shots/3335723-Mobile-Game-Map-UI-Animation) — Andru Gavrish for Loggia; an animated version of the same map metaphor, useful reference for transition/animation behavior between nodes.
- Browse more: [Game Map UI tag](https://dribbble.com/tags/game%20map%20ui); [Level Map search](https://dribbble.com/search/level-map); [Game Levels tag](https://dribbble.com/tags/game_levels).

**4. Isometric / 3D road and building maps**
- [Learning – line isometric illustration](https://dribbble.com/shots/13374331-Learning-line-isometric-illustration) — Boyko; isometric illustration built specifically around a "learning" theme.
- [Roadmap by NickZag](https://dribbble.com/shots/4366807-Roadmap) — a roadmap-titled shot surfaced specifically against isometric/game-roadmap search queries.
- [Isometric onboarding screen by herdetya](https://www.pinterest.com/pin/isometric-onboarding-screen-by-herdetya-on-dribbble--471892867206398435/) — a Pinterest repin pointing at a Dribbble isometric onboarding screen, useful for the isometric-onboarding-flow angle.
- Browse more: [Isometric search](https://dribbble.com/search/isometric); [Learning Isometric search](https://dribbble.com/search/learning-isometric); Pinterest [Discover 7 Isometric Screen Layout and isometric ideas](https://www.pinterest.com/scasper5/isometric-screen-layout/).

**5. Vertical timeline / stepper roadmaps (numbered steps, no winding curve)**
- [Steps / Roadmap Section – UI UX Redesign Process](https://dribbble.com/shots/23726304-Steps-Roadmap-Section-UI-UX-Redesign-Process) — Sameer Siddiqui; a clean numbered-step roadmap section.
- [Roadmap UI Design (Freebie)](https://dribbble.com/shots/19831308-Roadmap-UI-Design-Freebie) — Pierluigi Giglio; a free, reusable roadmap UI component set.
- [User Journey Map for the App](https://dribbble.com/shots/16714187-User-Journey-Map-for-the-App) — Kate Pine; a journey-map treatment of the same step-sequence idea.
- Browse more: [Roadmap tag](https://dribbble.com/tags/roadmap); [Road Map tag](https://dribbble.com/tags/road-map); [UX Roadmap search](https://dribbble.com/search/ux-roadmap); [User Journey Map tag](https://dribbble.com/tags/user_journey_map).

**6. Card-based connected roadmap flows (grid/Kanban, non-path)**
- [Roadmap Feature Cards](https://dribbble.com/shots/14780471--Roadmap-Feature-Cards) — Peter Pilat for Productboard; a real SaaS product's roadmap card system (strongest "professional/enterprise" polish in this set).
- [Course Cards](https://dribbble.com/shots/6246423-Course-Cards) — Jamie Ritchie; course content presented as a connected card grid rather than a path.
- [52 UX Flow Cards](https://dribbble.com/shots/4825537-52-UX-Flow-Cards) — Amin Zia; a broad card-flow system usable as a component-level reference.
- [Userflow Cards](https://dribbble.com/shots/2694358-Userflow-Cards) — Andrew Gormley; another card-flow visualization of sequential user steps.

**7. Pinterest curated boards/mood-boards (breadth across multiple styles above)**
- [9 UI/UX roadmap ideas](https://in.pinterest.com/aman204/uiux-roadmap/) — small board mixing roadmap infographic styles.
- [22 Learning Journey ideas](https://uk.pinterest.com/dianecorrigan3341/learning-journey/) — board specifically curated around "learning journey" visuals.
- [Educational Map Design Ideas](https://in.pinterest.com/ideas/educational-map-design-ideas/926784960311/) — Pinterest "Ideas" hub aggregating educational map-style visuals.
- [Roadmap 3.0 Course](https://es.pinterest.com/ideas/roadmap-3.0-course/934405819462/) — another Pinterest "Ideas" hub, course-roadmap themed.
- Lower-relevance but found (flagged, not recommended as primary references): [UX/UI Design learning roadmap](https://uk.pinterest.com/pin/935411785128514206/), [UX/UI Roadmap Template for Organizing Design Projects](https://kr.pinterest.com/pin/434878907773206871/) / [duplicate pin](https://www.pinterest.com/pin/434878907773206871/), [Career Development Path Roadmap for PowerPoint](https://uk.pinterest.com/pin/career-development-path-roadmap-for-powerpoint--674484481708631029/), and [120 Product Roadmap Templates, Examples & Ideas](https://ca.pinterest.com/venngage/product-roadmap-templates-examples-ideas/) — these are generic business/career-roadmap infographic templates (how-to-become-a-designer roadmaps, product-management timeline templates), not gamified in-product learner-path UI. Included only so the developer knows they exist and can rule them out quickly rather than clicking in blind.

### Inferences
- The single strongest category match for the user's exact ask (winding road, sequential milestones, lock/current/complete coloring, K-12/gamified) is category 1 (Duolingo-style) followed by category 3 (game level-select maps) — both are real shipped consumer products with mascots/game tone, not abstract business-roadmap templates.
- Dribbble consistently outperformed Pinterest for finding individually strong, attributable, polished shots; Pinterest was more useful for category 7 (breadth/mood-boarding) and for surfacing Duolingo-specific sub-boards, but a large fraction of raw Pinterest hits for "roadmap"/"learning path" terms are generic professional-development infographics rather than app UI.

### Gaps
- Could not rank shots by Dribbble "likes"/popularity or confirm publish dates, since no fetch tool could render Dribbble pages; ranking above within each category is based on relevance/specificity of the title and snippet to the brief, not a verified popularity metric.
- Could not verify current link validity by visiting each URL (fetch tools failed on every Dribbble/Pinterest domain tried); all URLs are exactly as returned by the search tool and were not independently re-confirmed to load. If any have since been taken down or renamed, the search-engine snapshot is the only evidence of their prior existence.
- No isometric-roadmap shot was found with the same depth of confirmation as the other categories (only 2-3 candidates vs. 4-6 for other styles) — this category is thinner in the source material and may need a follow-up Dribbble-side manual browse of the isometric/learning-isometric search pages listed above.
