<?php

/*
|--------------------------------------------------------------------------
| Pedagogical workflow pilot (Course Presentation card)
|--------------------------------------------------------------------------
|
| Data only. STAGED WORKFLOW SCOPE: Std 9 chapter 1 of Science (8592), Mathematics (8594), English (8622) and Social Science (8608), school 1.
| Other chapters enabled in course_presentation_pilot use the same generator and the general method/format choices, without these
| chapter-specific staged profiles. PedagogicalWorkflow::appliesTo() gates staged context; the backend also checks the chapter scope.
|
| `methods` hold the staged workflow of each teaching method (keys match config course_presentation_pilot.pedagogies
| and StudyDeck\TeachingMethodProfile). `formats` hold the delivery formats; a format is selectable only when its
| generator exists (course_presentation_pilot.generation.formats). `compatibility` is the method -> format map.
| Change a stage here; no code or React change is needed.
*/

$s = static fn (string $name, string $objective, string $teacher, string $student, string $activity, array $questions, string $interactions, string $visual, string $outcome, int $minutes): array => [
    'name' => $name, 'objective' => $objective, 'teacher' => $teacher, 'student' => $student, 'activity' => $activity,
    'questions' => $questions, 'interactions' => $interactions, 'visual' => $visual, 'outcome' => $outcome, 'minutes' => $minutes,
];

return [

    'scope' => [
        'label' => 'Std 9 Science, chapter 1 (Exploration: Entering the World of Secondary Science)',
        'chapter_id' => 8592, 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => 1,
        'academic_year' => 2026,
    ],

    // Further chapters the workflow is switched on for. `scope` above is the original Science chapter and uses the top-level `methods` and
    // `compatibility`; each chapter here names a subject `profile` (below) that narrows them to what suits the subject. A chapter matches only
    // when its id, standard, subject AND school all match, exactly like `scope`. Std 9 chapter 1 of every other subject is NOT listed here on purpose.
    'chapters' => [
        ['label' => 'Std 9 Mathematics, chapter 1 (Orienting Yourself: The Use of Coordinates)', 'profile' => 'mathematics',
            'chapter_id' => 8594, 'standard_id' => 42, 'subject_id' => 3976, 'sub_institute_id' => 1],
        ['label' => 'Std 9 English, chapter 1 (How I Taught My Grandmother to Read)', 'profile' => 'english',
            'chapter_id' => 8622, 'standard_id' => 42, 'subject_id' => 3978, 'sub_institute_id' => 1],
        ['label' => 'Std 9 Social Science, chapter 1 (Understanding Social Science)', 'profile' => 'social_science',
            'chapter_id' => 8608, 'standard_id' => 42, 'subject_id' => 4469, 'sub_institute_id' => 1],
    ],

    /*
    | SUBJECT PROFILES. What differs between subjects, nothing else:
    |   methods        the teaching methods offered for the chapter, strongest first (the order of the picker). A method left out is refused by the server.
    |   compatibility  method -> delivery formats that suit it in this subject (a format with no generator is listed but cannot be chosen).
    |   wording        text replaced in the method stages (the shared stage text was written for Science).
    |   images         'diagrams' = the normal picture ladder (web picture, textbook picture, drawn diagram); omitted = photographs first, as for Science.
    |   ai_note        appended to the method's instruction in every generation prompt.
    |
    | HOW THE METHODS WERE CHOSEN (2026-11-03). Two sources, nothing invented: (1) the chapter's own teaching analysis (semantic_intelligence), through
    | the same MethodSuitability rule the pre-flight shows: a method it judges NOT suitable is left out; (2) for the five methods that analysis cannot
    | judge (explicit, problem-based, collaborative, Socratic, mastery) the content of the chapter, kept only where the chapter has work for that method.
    | Add a method back by listing it in `methods` and `compatibility`; no code changes.
    |   Maths (9 concepts)        suitable: inquiry 8/9, experiential 9/9, gamified 5/9. Out: project 0/9, flipped 0/9; Socratic and a story-led
    |                             format do not fit plotting and formulas.
    |   English (10 concepts)     suitable: experiential 10/10, gamified 2/10. Out: inquiry 0/10, project 0/10, flipped 1/10; problem-based has no
    |                             problem to solve in a story.
    |   Social Science (17)       suitable: inquiry 15/17, experiential 14/17, project 4/17. Out: flipped 0/17, gamified 1/17; mastery drilling does not
    |                             fit a chapter of definitions and sources.
    | Only interactive_slides and branching_scenario can be generated today; the other formats are shown so the teacher sees what would suit.
    */
    'profiles' => [

        'mathematics' => [
            'subject' => 'Mathematics',
            'methods' => ['explicit_instruction', 'inquiry_based', 'experiential', 'differentiated_mastery', 'gamified', 'problem_based', 'collaborative'],
            'compatibility' => [
                'explicit_instruction' => ['interactive_slides', 'worksheet_guide', 'video_lesson'],
                'inquiry_based' => ['interactive_slides', 'simulation_activity'],
                'experiential' => ['simulation_activity', 'interactive_slides', 'worksheet_guide'],
                'differentiated_mastery' => ['worksheet_guide', 'interactive_slides'],
                'gamified' => ['branching_scenario', 'interactive_slides', 'simulation_activity'],
                'problem_based' => ['interactive_slides', 'scenario_lesson', 'branching_scenario'],
                'collaborative' => ['interactive_slides', 'worksheet_guide', 'simulation_activity'],
            ],
            'wording' => ['scientific' => 'mathematical'],
            'images' => 'diagrams',
            'ai_note' => 'This is a Mathematics chapter. Teach through worked examples and plotting or construction steps, and show the working. Every picture is a drawn grid, graph or diagram, never a photograph; where a stage asks for a photograph, use a drawn diagram of the same idea. Use only numbers, coordinates and formulas that appear in the chapter text, its questions or its concept definitions (the finished deck is checked for this).',
        ],

        'english' => [
            'subject' => 'English',
            'methods' => ['experiential', 'explicit_instruction', 'collaborative', 'socratic', 'gamified', 'differentiated_mastery'],
            'compatibility' => [
                'experiential' => ['interactive_slides', 'branching_scenario', 'worksheet_guide'],
                'explicit_instruction' => ['interactive_slides', 'worksheet_guide', 'video_lesson'],
                'collaborative' => ['interactive_slides', 'branching_scenario', 'worksheet_guide'],
                'socratic' => ['socratic_dialogue', 'interactive_slides', 'branching_scenario'],
                'gamified' => ['branching_scenario', 'interactive_slides', 'scenario_lesson'],
                'differentiated_mastery' => ['worksheet_guide', 'interactive_slides'],
            ],
            'wording' => ['scientific' => 'literary'],
            'images' => 'diagrams',
            'ai_note' => 'This is an English chapter: a first-person story with language work (vocabulary, prefixes, tense, idioms). Ground every slide in the story itself: use only its characters, events and quotations. Teach each language point with sentences taken from or closely modelled on the chapter, and ask comprehension and discussion questions whose answers are reasons found in the text. Pictures are illustrations of scenes or drawn language diagrams; do not invent facts about real people or places.',
        ],

        'social_science' => [
            'subject' => 'Social Science',
            'methods' => ['inquiry_based', 'experiential', 'project_based', 'problem_based', 'collaborative', 'socratic', 'explicit_instruction'],
            'compatibility' => [
                'inquiry_based' => ['interactive_slides', 'branching_scenario', 'scenario_lesson', 'worksheet_guide'],
                'experiential' => ['simulation_activity', 'interactive_slides', 'worksheet_guide'],
                'project_based' => ['interactive_slides', 'worksheet_guide', 'scenario_lesson'],
                'problem_based' => ['interactive_slides', 'scenario_lesson', 'branching_scenario'],
                'collaborative' => ['interactive_slides', 'branching_scenario', 'worksheet_guide'],
                'socratic' => ['socratic_dialogue', 'interactive_slides', 'branching_scenario'],
                'explicit_instruction' => ['interactive_slides', 'video_lesson', 'worksheet_guide'],
            ],
            'wording' => ['scientific' => 'social-science'],
            'ai_note' => 'This is a Social Science chapter that introduces history, geography, political science and economics. Treat evidence as sources (what a source is, what it can and cannot tell us) and ground every claim in the chapter text; do not add dates, places or events it does not give. For the Indian knowledge-tradition ideas (Panchayati Raj, Dharma and Rajadharma, Vasudhaiva Kutumbakam) say only what the chapter says. Keep a neutral, respectful tone on social and political topics. Pictures are photographs, maps or drawn diagrams of the sources and places the chapter names.',
        ],
    ],

    'formats' => [
        'interactive_slides' => [
            'name' => 'PPT / interactive slides', 'description' => 'A teacher-led slide lesson with an interaction on each slide.',
            'generator_key' => 'lms:generate-study-deck', 'structures' => ['stage_slide_groups', 'interactions'],
            'interaction_capabilities' => ['reveal', 'steps', 'compare', 'hotspots', 'match', 'order', 'scenario'],
            'structure' => 'Group the slides by workflow stage, in the stage order. Every stage gets its own run of slides; the slide title or first line names the stage activity, and the stage questions become the slide checks.',
        ],
        'branching_scenario' => [
            'name' => 'Interactive branching scenario', 'description' => 'A story in which the student makes decisions and sees different outcomes.',
            'generator_key' => 'lms:generate-study-deck --template=story_branching_scenario', 'structures' => ['story_acts', 'decisions', 'endings'],
            'interaction_capabilities' => ['decision', 'feedback', 'multiple_paths'],
            'structure' => 'Build the story so its scenes follow the workflow stages in order. Each stage becomes at least one scene; the student decisions are the stage questions, each choice leads to a different consequence with feedback, and the ending states the conceptual conclusion.',
        ],
        'scenario_lesson' => [
            'name' => 'Scenario-based lesson', 'description' => 'A lesson built around decision nodes in a real situation.',
            'generator_key' => null, 'structures' => ['decision_nodes'], 'interaction_capabilities' => ['decision'],
        ],
        'video_lesson' => [
            'name' => 'Video lesson', 'description' => 'A narrated video lesson.',
            'generator_key' => null, 'structures' => ['video'], 'interaction_capabilities' => [],
        ],
        'simulation_activity' => [
            'name' => 'Simulation / practical activity', 'description' => 'A hands-on simulation or practical.',
            'generator_key' => null, 'structures' => ['simulation'], 'interaction_capabilities' => ['parameters'],
        ],
        'worksheet_guide' => [
            'name' => 'Student worksheet / learning guide', 'description' => 'A printable guide students work through.',
            'generator_key' => null, 'structures' => ['document'], 'interaction_capabilities' => [],
        ],
        'socratic_dialogue' => [
            'name' => 'Interactive Socratic dialogue', 'description' => 'A question chain that adapts to student reasoning.',
            'generator_key' => null, 'structures' => ['dialogue'], 'interaction_capabilities' => ['adaptive_questions'],
        ],
    ],

    'compatibility' => [
        'explicit_instruction' => ['interactive_slides', 'video_lesson', 'worksheet_guide'],
        'inquiry_based' => ['interactive_slides', 'simulation_activity', 'branching_scenario', 'socratic_dialogue'],
        'problem_based' => ['interactive_slides', 'scenario_lesson', 'branching_scenario'],
        'project_based' => ['interactive_slides', 'worksheet_guide', 'scenario_lesson'],
        'experiential' => ['simulation_activity', 'interactive_slides', 'worksheet_guide'],
        'collaborative' => ['interactive_slides', 'branching_scenario', 'worksheet_guide'],
        'socratic' => ['socratic_dialogue', 'interactive_slides', 'branching_scenario'],
        'flipped_classroom' => ['video_lesson', 'worksheet_guide', 'interactive_slides'],
        'differentiated_mastery' => ['worksheet_guide', 'interactive_slides', 'scenario_lesson'],
        'gamified' => ['branching_scenario', 'interactive_slides', 'scenario_lesson'],
    ],

    'methods' => [

        'explicit_instruction' => [
            'name' => 'Explicit instruction / direct teaching',
            'description' => 'The teacher states the goal, explains and models each idea, then students practise with decreasing help.',
            'objectives' => ['Students can state each concept accurately', 'Students can reproduce a modelled procedure alone', 'Students show mastery on a short check'],
            'teacher_role' => 'Explains clearly, models step by step, checks understanding and corrects at once.',
            'student_role' => 'Listens actively, follows the model, practises and answers checks.',
            'activity_types' => ['worked example', 'guided practice', 'independent practice', 'recap'],
            'interaction_types' => ['steps', 'reveal', 'order'],
            'assessment' => 'Short check after guided practice and a closing assessment on every concept.',
            'ai_instructions' => 'Lead with explanation, then model, then practice. Never open with an open investigation.',
            'active' => true,
            'stages' => [
                $s('Introduction', 'Connect the chapter to what students already know', 'Greet the topic with one relevant question and link it to earlier learning.', 'Share what you already know or have seen.', 'Two-minute recall of earlier ideas related to the chapter.', ['What do you already know about this idea?', 'Where have you met it before?'], 'reveal', 'One clear photograph or diagram that frames the chapter.', 'Students recall relevant prior knowledge.', 3),
                $s('Learning objectives', 'Make the success criteria visible', 'State what students will be able to do by the end, in plain words.', 'Read the objectives and restate one in your own words.', 'Objective list matched to the chapter outcomes.', ['Which objective seems hardest to you?'], 'reveal', 'None needed; keep the slide uncluttered.', 'Students know what success looks like.', 2),
                $s('Concept explanation', 'Explain each chapter concept accurately', 'Explain one concept at a time in order of dependence, with a labelled picture.', 'Listen, take notes and ask about unclear words.', 'Short explanation of each concept with its key terms.', ['Can you say the idea in one sentence?', 'What is the key term here?'], 'reveal, compare', 'A labelled picture or diagram for every concept.', 'Students can state each concept.', 10),
                $s('Teacher demonstration', 'Model the thinking or procedure', 'Work one example aloud, showing every step and why.', 'Watch and copy the steps in your notes.', 'Numbered worked example drawn from the chapter.', ['Why was this step done first?', 'What would go wrong if we skipped it?'], 'steps', 'Step-by-step figure of the worked example.', 'Students can follow the procedure.', 6),
                $s('Guided practice', 'Practise with teacher support', 'Give a similar problem, prompt gently and correct mistakes immediately.', 'Try the problem with hints and compare with the model.', 'One shared problem with graded hints.', ['What is your first step?', 'Which hint helped you?'], 'steps, order', 'Partially completed figure students finish.', 'Students apply the idea with support.', 7),
                $s('Independent practice', 'Practise alone', 'Circulate, watch for errors, give no hints unless stuck.', 'Solve the practice questions on your own.', 'Short set of chapter questions of rising difficulty.', ['Solve this without help.', 'Explain your answer in a line.'], 'match, order', 'None; keep attention on the task.', 'Students show independent success.', 8),
                $s('Assessment', 'Check mastery of every concept', 'Give a short quiz or exit questions and note who needs help.', 'Answer the check honestly.', 'Exit ticket with one question per concept.', ['Which concept are you least sure about?'], 'reveal', 'None.', 'Teacher knows who has mastered each concept.', 5),
                $s('Recap', 'Consolidate the lesson', 'Summarise the main ideas and link back to the objectives.', 'Say the three main ideas in your own words.', 'Closing summary and preview of the next lesson.', ['What are the three big ideas today?'], 'reveal', 'Summary figure of the chapter.', 'Students leave with a clear summary.', 3),
            ],
        ],

        'inquiry_based' => [
            'name' => 'Inquiry-based learning',
            'description' => 'Students find ideas out by questioning, investigating evidence and explaining it before the concept is named.',
            'objectives' => ['Students ask investigable questions', 'Students use evidence to build explanations', 'Students apply the idea to a new situation'],
            'teacher_role' => 'Provokes curiosity, asks, facilitates and withholds the answer until evidence is gathered.',
            'student_role' => 'Observes, predicts, investigates, and explains from evidence.',
            'activity_types' => ['observation', 'investigation', 'evidence comparison', 'explanation', 'application'],
            'interaction_types' => ['hotspots', 'compare', 'scenario', 'reveal'],
            'assessment' => 'Conceptual questions that ask students to cite their evidence.',
            'ai_instructions' => 'Never state the concept before the evidence stage. Each topic opens with something to observe and one investigable question, with a prediction prompt.',
            'active' => true,
            'stages' => [
                $s('Engage: curiosity creation', 'Provoke curiosity about the chapter', 'Show a surprising picture or situation and say nothing about the explanation.', 'Look closely and say what you notice and wonder.', 'Curiosity-provoking opening observation.', ['What do you notice?', 'What puzzles you?'], 'hotspots', 'A striking real photograph that raises a question.', 'Students are curious and have noticed details.', 5),
                $s('Ask: driving questions', 'Form investigable questions', 'Help students turn wonderings into one driving question and ask for a prediction.', 'Suggest questions and write a hypothesis: what do you think and why?', 'Driving question and prediction prompt.', ['What do you think will happen, and why?', 'How could we find out?'], 'reveal', 'The same picture with the question overlaid.', 'Students hold a testable prediction.', 5),
                $s('Explore: investigation', 'Gather evidence', 'Provide the materials or data; stay a guide and ask about what students see.', 'Investigate, record observations and compare with your prediction.', 'Observation activity with a recording table.', ['What did you observe?', 'Does it match your prediction?'], 'hotspots, compare', 'Photograph or data figure students examine.', 'Students hold recorded evidence.', 10),
                $s('Explain: concept discovery', 'Name the concept the evidence supports', 'Ask what the evidence suggests, then state the concept in the scientific words.', 'Explain the result using your evidence, then meet the key term.', 'Claim-evidence-reasoning explanation.', ['What does your evidence show?', 'What can we say for certain?'], 'reveal, compare', 'Labelled diagram that matches the evidence.', 'Students can state the concept with evidence.', 8),
                $s('Elaborate: real-life application', 'Transfer the idea', 'Present a new situation where the idea applies.', 'Apply the idea to the new situation.', 'Real-life application task.', ['Where else do you see this idea?', 'What would change if the conditions were different?'], 'scenario', 'Everyday photograph of the application.', 'Students transfer the idea.', 7),
                $s('Evaluate: assessment', 'Assess understanding of concepts', 'Ask conceptual questions that need reasoning, not recall.', 'Answer and justify with evidence.', 'Conceptual assessment with reasoning prompts.', ['What evidence supports your answer?', 'What would you test next?'], 'reveal', 'None.', 'Teacher sees conceptual understanding.', 5),
            ],
        ],

        'problem_based' => [
            'name' => 'Problem-based learning',
            'description' => 'Students learn the chapter by solving an authentic problem posed before any explanation.',
            'objectives' => ['Students analyse a real-world problem', 'Students find and use knowledge to solve it', 'Students defend and reflect on their solution'],
            'teacher_role' => 'Poses the problem, coaches the process and does not hand over the solution.',
            'student_role' => 'Defines the problem, finds what is missing, researches and proposes solutions.',
            'activity_types' => ['problem analysis', 'research', 'solution design', 'solution comparison', 'reflection'],
            'interaction_types' => ['scenario', 'steps', 'compare'],
            'assessment' => 'Quality of the solution and the reasoning behind it.',
            'ai_instructions' => 'Pose the problem first. Concept explanation exists to equip students to solve it. Never explain before the problem is posed.',
            'active' => true,
            'stages' => [
                $s('Present a real-world problem', 'Hook students with an authentic problem', 'Pose a realistic problem that needs the chapter ideas.', 'Read the problem and say what is being asked.', 'Problem statement with a realistic setting.', ['What is the problem?', 'Who is affected?'], 'scenario', 'Photograph of the real situation.', 'Students hold the problem in mind.', 5),
                $s('Understand the problem', 'Define known and unknown', 'Guide a known / unknown / goal analysis.', 'List what you know, what you need, and the goal.', 'Known and unknown table.', ['What facts do we already have?', 'What is unclear?'], 'compare', 'Annotated problem diagram.', 'Students state the problem precisely.', 5),
                $s('Identify knowledge gaps', 'Decide what to learn', 'Ask which ideas are missing to solve it.', 'Write the questions you must answer first.', 'List of learning issues.', ['What do we need to find out?'], 'reveal', 'None.', 'Students have a research agenda.', 4),
                $s('Research and investigation', 'Learn the needed concepts', 'Provide the chapter material and coach; explain only when stuck.', 'Read, investigate and note what answers your questions.', 'Guided research using chapter text and pictures.', ['What did you find out?', 'How does it help?'], 'steps, hotspots', 'Labelled diagrams of the concepts.', 'Students understand the needed concepts.', 10),
                $s('Develop solutions', 'Build candidate solutions', 'Ask for more than one option.', 'Design and test your solutions on paper.', 'Solution design task.', ['What could we do?', 'What are the trade-offs?'], 'scenario, steps', 'Sketch space for the solution.', 'Students hold at least two options.', 8),
                $s('Present solutions', 'Compare and defend options', 'Facilitate a comparison against the goal.', 'Present your best solution and give reasons.', 'Solution comparison table.', ['Which solution is best, and why?'], 'compare', 'Comparison chart.', 'Students defend a choice with reasons.', 7),
                $s('Reflection', 'Reflect on solving', 'Ask what worked and what they would do differently.', 'Write what you learned and how you solved it.', 'Reflection prompt.', ['What did you learn about the idea?', 'What would you change?'], 'reveal', 'None.', 'Students name the learning.', 4),
            ],
        ],

        'project_based' => [
            'name' => 'Project-based learning',
            'description' => 'Students answer a driving question by planning, building and presenting something of their own.',
            'objectives' => ['Students plan a project', 'Students apply chapter ideas to build a product', 'Students present and reflect'],
            'teacher_role' => 'Frames the driving question, sets criteria and coaches.',
            'student_role' => 'Plans, researches, builds and presents.',
            'activity_types' => ['project planning', 'research', 'design', 'creation', 'presentation'],
            'interaction_types' => ['steps', 'reveal', 'scenario'],
            'assessment' => 'A rubric on the product, the presentation and the use of chapter ideas.',
            'ai_instructions' => 'The lesson reads as a plan for student-made work. Tie every stage to the driving question; teach only what the project needs.',
            'active' => true,
            'stages' => [
                $s('Driving question', 'Launch the project', 'Pose the open driving question and the success criteria.', 'Restate the question and what you must produce.', 'Project launch with criteria.', ['What are we trying to find out or make?'], 'reveal', 'Photograph that frames the project.', 'Students know the goal.', 5),
                $s('Project planning', 'Plan the work', 'Help teams set tasks, roles and time.', 'Write a plan with steps and deadlines.', 'Plan template.', ['What are the steps?', 'Who does what?'], 'steps', 'Planning chart.', 'Students hold a workable plan.', 6),
                $s('Research', 'Gather what is needed', 'Point to chapter sources and check notes.', 'Collect facts that answer the driving question.', 'Research with source notes.', ['What did you learn that you can use?'], 'hotspots', 'Labelled diagrams from the chapter.', 'Students hold accurate facts.', 8),
                $s('Design', 'Design the product', 'Ask for a design linked to the chapter ideas.', 'Sketch the product and justify choices.', 'Design sketch.', ['Why did you choose this design?'], 'scenario', 'Example design sketch.', 'Students hold a justified design.', 7),
                $s('Project creation', 'Build the product', 'Coach and watch progress against the plan.', 'Make the product.', 'Build time with checkpoints.', ['Are you on track?'], 'steps', 'Photograph of an example.', 'Students complete the product.', 15),
                $s('Presentation', 'Share the work', 'Set up the audience and the feedback form.', 'Present and answer questions.', 'Short presentations.', ['How does your product answer the question?'], 'reveal', 'None.', 'Students explain their product.', 10),
                $s('Assessment', 'Assess product and learning', 'Use the rubric and a reflection.', 'Reflect on what you learned and did well.', 'Rubric and reflection.', ['What would you improve?'], 'reveal', 'None.', 'Teacher has evidence of learning.', 5),
            ],
        ],

        'experiential' => [
            'name' => 'Experiential / learning by doing',
            'description' => 'Students act first, reflect on the experience and then form and test concepts (Kolb cycle).',
            'objectives' => ['Students carry out a hands-on task', 'Students reflect on what happened', 'Students link the experience to concepts and real life'],
            'teacher_role' => 'Sets up a safe task, observes and asks reflective questions.',
            'student_role' => 'Does, observes, reflects and applies.',
            'activity_types' => ['hands-on task', 'observation record', 'reflection', 'experiment', 'application'],
            'interaction_types' => ['steps', 'hotspots', 'scenario'],
            'assessment' => 'Observation records and reflections.',
            'ai_instructions' => 'Students act first; explain only after the doing. Use only ordinary, safe materials suitable for school.',
            'active' => true,
            'stages' => [
                $s('Concrete experience', 'Do a hands-on task', 'Give materials and safety rules, then step back.', 'Carry out the task step by step.', 'Hands-on task with materials list.', ['What did you do?'], 'steps', 'Photograph of the set-up.', 'Students have a shared experience.', 12),
                $s('Observation', 'Record what happened', 'Prompt careful recording.', 'Write or draw what you observed.', 'Observation table.', ['What did you see, hear or measure?'], 'hotspots', 'Photograph of results.', 'Students hold observations.', 6),
                $s('Reflection', 'Think about the experience', 'Ask open reflective questions.', 'Discuss what surprised you.', 'Reflection discussion.', ['What surprised you?', 'Why do you think it happened?'], 'reveal', 'None.', 'Students link cause and effect.', 6),
                $s('Conceptualization', 'Form the concept', 'Connect the experience to the scientific idea.', 'Explain the idea using your results.', 'Short concept explanation.', ['What idea explains the result?'], 'reveal, compare', 'Labelled diagram.', 'Students can state the concept.', 7),
                $s('Experimentation', 'Test the idea', 'Ask for a variation to test.', 'Change one thing and predict the result.', 'Variation task.', ['What will change if we alter this?'], 'scenario', 'Photograph of the variation.', 'Students test the idea.', 8),
                $s('Application', 'Use the idea in real life', 'Link to everyday life.', 'Give a real-life example.', 'Real-life application prompt.', ['Where do you see this at home or school?'], 'scenario', 'Everyday photograph.', 'Students apply the idea.', 6),
            ],
        ],

        'collaborative' => [
            'name' => 'Collaborative / peer learning',
            'description' => 'Students learn together in groups with shared goals, roles and peer feedback.',
            'objectives' => ['Students work to a shared goal', 'Students explain ideas to peers', 'Students give constructive peer feedback'],
            'teacher_role' => 'Forms groups, sets roles, monitors and prompts discussion.',
            'student_role' => 'Takes a role, contributes, teaches peers and assesses peers.',
            'activity_types' => ['group task', 'discussion', 'jigsaw sharing', 'presentation', 'peer assessment'],
            'interaction_types' => ['compare', 'match', 'reveal'],
            'assessment' => 'Individual accountability plus peer assessment.',
            'ai_instructions' => 'Write tasks groups can split between roles (reader, recorder, reporter). Check prompts are things the group must agree on.',
            'active' => true,
            'stages' => [
                $s('Group formation', 'Form balanced groups', 'Make mixed groups of 3 to 4 and assign roles.', 'Take your role.', 'Group and role cards.', ['What does your role do?'], 'reveal', 'None.', 'Groups are ready.', 4),
                $s('Shared learning goal', 'Agree a common goal', 'State the group goal and success criteria.', 'Restate the goal as a group.', 'Goal card.', ['What must we finish together?'], 'reveal', 'None.', 'Groups share a goal.', 3),
                $s('Collaborative activity', 'Work the task together', 'Give a task with parts for each member.', 'Complete your part and join it to the others.', 'Divided task using chapter material.', ['What is your part?'], 'match, compare', 'Diagram split into parts.', 'Groups produce a joint result.', 12),
                $s('Discussion', 'Discuss and agree', 'Prompt group agreement.', 'Discuss and agree an answer.', 'Structured group discussion.', ['Do we all agree? Why?'], 'compare', 'None.', 'Groups reach consensus.', 6),
                $s('Knowledge sharing', 'Teach across groups', 'Use jigsaw or gallery walk.', 'Teach your part to another group.', 'Jigsaw sharing.', ['Can you explain it to another group?'], 'reveal', 'Group posters.', 'Knowledge is shared.', 7),
                $s('Presentation', 'Present to the class', 'Time the presentations.', 'Present your joint result.', 'Short group presentations.', ['What is your group conclusion?'], 'reveal', 'Presentation slide.', 'Groups communicate results.', 8),
                $s('Peer assessment', 'Give feedback', 'Provide a feedback form.', 'Rate peers on the criteria and give one suggestion.', 'Peer feedback form.', ['What did your peers do well?'], 'reveal', 'None.', 'Students give useful feedback.', 5),
            ],
        ],

        'socratic' => [
            'name' => 'Socratic questioning / guided discovery',
            'description' => 'The teacher guides understanding by chains of questions instead of telling.',
            'objectives' => ['Students reason from their own ideas', 'Students test assumptions', 'Students reach a justified conclusion'],
            'teacher_role' => 'Asks, probes and withholds answers.',
            'student_role' => 'Answers, justifies, questions assumptions and refines.',
            'activity_types' => ['question chain', 'misconception probe', 'reasoning', 'reflection'],
            'interaction_types' => ['compare', 'reveal'],
            'assessment' => 'Quality of reasoning in answers.',
            'ai_instructions' => 'Lead each slide with a question; hold the explanation back; checks are chains of follow-up questions, never a quiz.',
            'active' => true,
            'stages' => [
                $s('Opening question', 'Open with one question', 'Ask a broad question about the idea.', 'Answer in your own words.', 'Opening question.', ['What is your first idea?'], 'reveal', 'A photograph that prompts the question.', 'Students voice an initial idea.', 4),
                $s('Initial understanding', 'Surface what students think', 'Ask what they mean and how they know.', 'Explain what you think and why.', 'Probing questions.', ['What do you mean by that?', 'How do you know?'], 'reveal', 'None.', 'Teacher sees current understanding.', 6),
                $s('Deeper questioning', 'Probe further', 'Ask for reasons and examples.', 'Give a reason and an example.', 'Follow-up question chain.', ['Can you give an example?', 'What follows from that?'], 'compare', 'None.', 'Students deepen their reasoning.', 7),
                $s('Challenge assumptions', 'Test assumptions', 'Present a contrasting case or common misconception.', 'Decide whether your idea still holds.', 'Misconception versus correct idea.', ['What if it were different?', 'What is the evidence against?'], 'compare', 'Contrasting picture.', 'Students revise a misconception.', 7),
                $s('Logical reasoning', 'Build the argument', 'Help students link their statements.', 'Join your statements into a logical argument.', 'Argument building.', ['Does this follow from that?'], 'reveal', 'Concept map.', 'Students form a coherent argument.', 6),
                $s('Reflection', 'Conclude and reflect', 'Summarise what moved.', 'Say how your idea changed.', 'Reflection.', ['How has your thinking changed?'], 'reveal', 'None.', 'Students state a guided conclusion.', 4),
            ],
        ],

        'flipped_classroom' => [
            'name' => 'Flipped classroom',
            'description' => 'Students meet the content before class; class time is used for application and discussion.',
            'objectives' => ['Students arrive with the basics', 'Students apply ideas in class', 'Students solve problems together'],
            'teacher_role' => 'Checks pre-class learning and coaches application.',
            'student_role' => 'Prepares before class and applies during class.',
            'activity_types' => ['pre-class input', 'knowledge check', 'discussion', 'problem solving'],
            'interaction_types' => ['scenario', 'steps', 'compare'],
            'assessment' => 'Knowledge check then an in-class assessment.',
            'ai_instructions' => 'Assume the idea was met beforehand: confirm it in a line then move to application. Keep concept slides brief.',
            'active' => true,
            'stages' => [
                $s('Pre-class learning', 'Provide the pre-class input', 'Assign the short reading or video.', 'Study the input and note questions.', 'Pre-class material and notes.', ['What did you learn?', 'What was unclear?'], 'reveal', 'Key diagram of the input.', 'Students arrive prepared.', 5),
                $s('Knowledge check', 'Check pre-class learning', 'Give a quick check.', 'Answer the check.', 'Short recall check.', ['What is the main idea?'], 'match', 'None.', 'Teacher sees gaps.', 5),
                $s('Classroom discussion', 'Clear confusions', 'Discuss the unclear points.', 'Raise and discuss questions.', 'Discussion of misunderstandings.', ['What still confuses you?'], 'compare', 'Contrasting diagram.', 'Confusions are cleared.', 8),
                $s('Guided application', 'Apply with support', 'Guide an application task.', 'Apply the idea.', 'Application task.', ['How would you use the idea here?'], 'steps', 'Application photograph.', 'Students apply the idea.', 10),
                $s('Problem solving', 'Solve in pairs or groups', 'Set a harder problem.', 'Solve it together.', 'Collaborative problem.', ['What is your plan?'], 'scenario', 'Problem figure.', 'Students solve the problem.', 10),
                $s('Assessment', 'Assess learning', 'Give a short assessment.', 'Complete it alone.', 'Short assessment.', ['Show what you can do.'], 'reveal', 'None.', 'Teacher has evidence.', 5),
                $s('Reflection', 'Reflect on learning', 'Ask what helped.', 'Write what helped you.', 'Reflection.', ['What helped your learning?'], 'reveal', 'None.', 'Students name what helped.', 3),
            ],
        ],

        'differentiated_mastery' => [
            'name' => 'Differentiated / mastery-based instruction',
            'description' => 'Teaching adapts to diagnosed needs and students move on only after showing mastery.',
            'objectives' => ['Students are placed on the right path', 'Students master each concept', 'Students who need it get remediation or enrichment'],
            'teacher_role' => 'Diagnoses, groups, teaches targets and checks mastery.',
            'student_role' => 'Follows a personal path until mastery.',
            'activity_types' => ['diagnostic', 'tiered tasks', 'targeted teaching', 'mastery check'],
            'interaction_types' => ['steps', 'compare', 'match'],
            'assessment' => 'Diagnostic at the start and a mastery assessment at the end.',
            'ai_instructions' => 'Scaffold: give the simplest statement first, then the full idea. Give worked examples in graded steps and a model answer for teachers.',
            'active' => true,
            'stages' => [
                $s('Diagnostic assessment', 'Find the starting point', 'Give a short diagnostic across the concepts.', 'Answer to show what you know.', 'Diagnostic quiz.', ['What do you already know about each idea?'], 'match', 'None.', 'Teacher knows each student starting point.', 6),
                $s('Identify learning needs', 'Group by need', 'Group students by result.', 'See your learning path.', 'Need grouping.', ['Which ideas need work?'], 'reveal', 'None.', 'Students are on a path.', 3),
                $s('Personalized learning', 'Work at your level', 'Provide tiered tasks.', 'Do the tier set for you.', 'Tiered tasks.', ['Which task suits you?'], 'steps', 'Scaffolded figure.', 'Each student works at the right level.', 10),
                $s('Targeted instruction', 'Teach the gaps', 'Teach small groups the specific gap.', 'Learn the gap with help.', 'Mini lesson per group.', ['What is the simplest way to say it?'], 'compare', 'Simplified diagram.', 'Gaps are addressed.', 10),
                $s('Guided practice', 'Practise with feedback', 'Give graded practice and feedback.', 'Practise and use the feedback.', 'Graded practice.', ['Which step was hard?'], 'steps', 'None.', 'Students gain accuracy.', 8),
                $s('Mastery assessment', 'Check mastery', 'Give a mastery check with a model answer.', 'Show mastery.', 'Mastery check.', ['Can you do it without help?'], 'match', 'None.', 'Mastery is confirmed or not.', 6),
                $s('Remediation or enrichment', 'Close or extend', 'Reteach or extend.', 'Revisit or extend the idea.', 'Remediation or enrichment task.', ['What will you work on next?'], 'compare', 'Extension figure.', 'Every student has a next step.', 6),
            ],
        ],

        'gamified' => [
            'name' => 'Gamified learning',
            'description' => 'The lesson is a mission of challenges that get harder, with feedback and visible progress.',
            'objectives' => ['Students complete progressively harder challenges', 'Students use feedback to improve', 'Students reflect on what they achieved'],
            'teacher_role' => 'Game master: sets the mission, gives feedback and celebrates progress.',
            'student_role' => 'Player: makes decisions, learns from feedback and levels up.',
            'activity_types' => ['mission', 'challenge', 'decision', 'achievement'],
            'interaction_types' => ['match', 'order', 'scenario', 'reveal'],
            'assessment' => 'Challenge results and the final achievement check.',
            'ai_instructions' => 'Frame the lesson as levels with visible progress ("Level 2 of 5"). Encouraging, not childish; no emoji. Explanation each level needs goes just before it.',
            'active' => true,
            'stages' => [
                $s('Introduce the mission', 'Set the story and goal', 'Present the mission and the rules.', 'Accept the mission.', 'Mission briefing.', ['What is your mission?'], 'reveal', 'Mission picture.', 'Students want to start.', 4),
                $s('Present the challenge', 'Pose the first challenge', 'Give the challenge and what is needed.', 'Read the challenge.', 'Challenge card.', ['What do you need to know to clear it?'], 'reveal', 'None.', 'Students see the aim.', 4),
                $s('Learning activity', 'Learn what the level needs', 'Teach just enough.', 'Study the idea to clear the level.', 'Short learning activity.', ['What clue helps you?'], 'steps, hotspots', 'Labelled diagram.', 'Students hold the needed idea.', 8),
                $s('Student decision', 'Decide and act', 'Let students choose.', 'Choose an action and see the result.', 'Decision point.', ['Which choice will you make, and why?'], 'scenario, order', 'Decision picture.', 'Students apply the idea in a decision.', 7),
                $s('Feedback and progress', 'Give feedback', 'Give immediate feedback and show progress.', 'Read the feedback and retry if needed.', 'Feedback and progress bar.', ['What did you learn from the result?'], 'reveal', 'Progress indicator.', 'Students improve.', 5),
                $s('Achievement', 'Reward mastery', 'Run a final challenge and award the badge.', 'Clear the final challenge.', 'Final mastery challenge.', ['Can you clear the final challenge?'], 'match', 'Badge.', 'Mastery is shown.', 6),
                $s('Reflection', 'Reflect on the mission', 'Ask what worked.', 'Say what you learned.', 'Reflection.', ['What did you learn on the mission?'], 'reveal', 'None.', 'Students name the learning.', 3),
            ],
        ],
    ],
];
