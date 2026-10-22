<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Flows\FeesStudentDetailFlow;
use PHPUnit\Framework\TestCase;

/**
 * "Show the fee details for MARYA FIROZ KHAN PATHAN" must be read as being about ONE student,
 * while sentences about a class, a month or the whole school must not.
 */
class FeesStudentDetailFlowTest extends TestCase
{
    public function test_a_named_student_is_recognised(): void
    {
        foreach ([
            'Show the fee details for MARYA FIROZ KHAN PATHAN' => 'MARYA FIROZ KHAN PATHAN',
            'fee details of Asha Rao' => 'Asha Rao',
            'How much does Divya Iyer owe?' => 'Divya Iyer',
            'Is Asha Rao behind on fees?' => 'Asha Rao',
            'Has Divya Iyer paid?' => 'Divya Iyer',
            'pending fees for student Asha Rao please' => 'Asha Rao',
        ] as $sentence => $name) {
            $this->assertSame(['name' => $name], FeesStudentDetailFlow::target($sentence), $sentence);
        }
    }

    public function test_a_student_number_is_recognised(): void
    {
        $this->assertSame(['id' => 42], FeesStudentDetailFlow::target('Show fee details for student 42'));
        $this->assertSame(['id' => 7], FeesStudentDetailFlow::target('fees of student id 7'));
    }

    public function test_sentences_that_are_not_about_one_student_are_left_alone(): void
    {
        foreach ([
            'Show the fee summary',
            'Show fee details for class 5',
            'fees for this month',
            'pending fees for all students',
            'outstanding fees of the whole school',
            'fee details for the top 10 defaulters',
            'collection report for this year',
            'Which students owe the most?',
        ] as $sentence) {
            $this->assertNull(FeesStudentDetailFlow::target($sentence), $sentence);
        }
    }

    private function classMemory(): array
    {
        $class = ['standard_id' => 6, 'standard_name' => 'Class 6', 'outstanding' => 6687331];

        return [
            'last_result_set' => ['module' => 'fees', 'tool' => 'fees.position', 'singular' => 'class', 'id_field' => 'standard_id',
                'items' => [['position' => 1, 'id' => 6, 'title' => 'Class 6', 'row' => $class]]],
            'selected_record' => ['item' => ['position' => 1, 'id' => 6, 'title' => 'Class 6', 'row' => $class], 'singular' => 'class', 'module' => 'fees'],
        ];
    }

    public function test_asking_for_students_with_pending_fees_wants_individual_students(): void
    {
        foreach ([
            'Show students with pending fees',
            'Which students owe the most fees?',
            'Which students currently have pending or unpaid fees?',
            'Who owes the most?',
            'Show the top 5 defaulters',
        ] as $sentence) {
            $this->assertNotNull(FeesStudentDetailFlow::listTarget($sentence, []), $sentence);
        }

        $this->assertSame(5, FeesStudentDetailFlow::listTarget('Show the top 5 defaulters', [])['limit']);
        $this->assertSame(10, FeesStudentDetailFlow::listTarget('Which students owe the most fees?', [])['limit']);
        $this->assertNull(FeesStudentDetailFlow::listTarget('Which students owe the most fees?', [])['standard_id']);
    }

    public function test_class_wise_summary_count_and_reminder_questions_keep_their_own_answers(): void
    {
        foreach ([
            'Show the fee summary',
            'Show classes with most pending fees',
            'How many students have pending fees?',
            'Show the collection report',
            'Send an SMS to students with pending fees',
            'Show the fee details for Class 6',
        ] as $sentence) {
            $this->assertNull(FeesStudentDetailFlow::listTarget($sentence, []), $sentence);
        }
    }

    public function test_with_a_class_on_screen_the_students_fee_detail_means_that_classs_students(): void
    {
        $list = FeesStudentDetailFlow::listTarget("Show me the student's fee detail", $this->classMemory());

        $this->assertSame(6, $list['standard_id']);
        $this->assertNotNull(FeesStudentDetailFlow::listTarget('Show students in this class', $this->classMemory()));
        // Without a class, a bare "the student's fee detail" is not a list request.
        $this->assertNull(FeesStudentDetailFlow::listTarget("Show me the student's fee detail", []));
    }

    public function test_a_class_named_in_the_question_is_found_from_the_classes_just_listed(): void
    {
        $memory = $this->classMemory();
        unset($memory['selected_record']);

        $list = FeesStudentDetailFlow::listTarget('Show students with pending fees in Class 6', $memory);

        $this->assertSame(6, $list['standard_id']);
    }

    public function test_a_selected_class_is_a_class_and_never_a_student(): void
    {
        $this->assertSame(6, \App\Domain\AI\Lifecycle\Flows\FeesRemindersFlow::selectedClassId($this->classMemory()));
        $this->assertNull(\App\Domain\AI\Lifecycle\Flows\FeesRemindersFlow::selectedClassId([
            'selected_record' => ['item' => ['id' => 9, 'row' => ['student_id' => 9]], 'singular' => 'student'],
        ]));
    }

    private function studentListMemory(): array
    {
        $rows = [
            ["student_id" => 1116490, "student_name" => "A", "outstanding" => 34814],
            ["student_id" => 1116491, "student_name" => "B", "outstanding" => 34814],
        ];

        return ["last_result_set" => [
            "module" => "fees", "tool" => "fees.outstanding_accounts", "singular" => "student", "id_field" => "student_id",
            "items" => array_map(fn ($r, $n) => ["position" => $n + 1, "id" => $r["student_id"], "title" => $r["student_name"], "row" => $r], $rows, array_keys($rows)),
        ]];
    }

    public function test_the_details_of_these_students_means_the_students_just_listed(): void
    {
        foreach (["Now show the details of these students.", "Show the fee details of those students", "show the fee details for them"] as $sentence) {
            $list = FeesStudentDetailFlow::listTarget($sentence, $this->studentListMemory());

            $this->assertSame([1116490, 1116491], $list["student_ids"], $sentence);
            $this->assertSame(2, $list["limit"]);
        }

        // With nothing listed there is no "these" to mean.
        $this->assertArrayNotHasKey("student_ids", FeesStudentDetailFlow::listTarget("Show students with pending fees", []) ?? []);
        // A list of classes is not a list of students.
        $this->assertNull(FeesStudentDetailFlow::listTarget("Now show the details of these students.", $this->classMemory()) === null ? null : (FeesStudentDetailFlow::listTarget("Now show the details of these students.", $this->classMemory())["student_ids"] ?? null));
    }
}
