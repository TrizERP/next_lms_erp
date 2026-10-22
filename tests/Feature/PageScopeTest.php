<?php

namespace Tests\Feature;

use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A page that answers only its own module sends another module's question to that module's page.
 *
 * READ-ONLY: the resolver reads the module registry and writes nothing.
 */
class PageScopeTest extends TestCase
{
    /** @return array{page:string, asked:string}|null */
    private function outside(string $question, string $page): ?array
    {
        $resolver = app(ModuleResolver::class);
        $resolution = $resolver->resolve($question, ['module' => $page], 1);
        $outside = $resolver->outsideThisPage($resolution, 1);

        return $outside === null ? null : ['page' => $outside['page']->key, 'asked' => $outside['asked']->key];
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function crossPageQuestions(): array
    {
        return [
            'fees on attendance' => ['Which students have pending fees?', 'attendance', 'fees'],
            'timetable on attendance' => ['Show the timetable for class 6', 'attendance', 'timetable'],
            'hostel on attendance' => ['Show hostel occupancy', 'attendance', 'hostel'],
            'attendance on fees' => ['How many students are absent today?', 'fees', 'attendance'],
            'timetable on fees' => ['Is any teacher double booked in the timetable?', 'fees', 'timetable'],
            'fees on timetable' => ['Which students owe the most fees?', 'timetable', 'fees'],
            'timetable on hostel' => ['Show the timetable for class 6', 'hostel', 'timetable'],
            'attendance on hostel' => ['Who was absent yesterday?', 'hostel', 'attendance'],
        ];
    }

    #[DataProvider('crossPageQuestions')]
    public function test_a_question_about_another_page_is_pointed_at_that_page(string $question, string $page, string $asked): void
    {
        $this->assertSame(['page' => $page, 'asked' => $asked], $this->outside($question, $page));
    }

    /** @return array<string, array{0:string,1:string}> */
    public static function questionsThatStay(): array
    {
        return [
            'attendance on attendance' => ['Show attendance for Class 6', 'attendance'],
            'absent on attendance' => ['How many students are absent today?', 'attendance'],
            'fees on fees' => ['Which students have pending fees?', 'fees'],
            'hostel on hostel' => ['Show hostel occupancy', 'hostel'],
            'timetable on timetable' => ['Is any teacher double booked?', 'timetable'],
            // Names no module at all.
            'general knowledge' => ['What is the capital of India?', 'attendance'],
            'elliptical follow-up' => ['Show me their names.', 'attendance'],
            'next follow-up' => ['What about Class 6?', 'attendance'],
            // A generic noun is not a page of its own, so it cannot bounce a question away.
            'generic students noun' => ['Show students in class 6', 'attendance'],
        ];
    }

    #[DataProvider('questionsThatStay')]
    public function test_a_question_that_belongs_here_or_names_no_page_is_not_redirected(string $question, string $page): void
    {
        $this->assertNull($this->outside($question, $page));
    }

    public function test_a_page_outside_the_strict_list_keeps_the_older_behaviour(): void
    {
        $this->assertNull($this->outside('Which students have pending fees?', 'dashboard'));
    }
}
