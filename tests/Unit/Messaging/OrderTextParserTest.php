<?php

namespace Tests\Unit\Messaging;

use App\Services\Messaging\OrderTextParser;
use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class OrderTextParserTest extends TestCase
{
    private function parse(string $text)
    {
        return (new OrderTextParser())->parse($text);
    }

    public function test_quantity_before_product_with_greetings_and_units(): void
    {
        $r = $this->parse("salam, 2 perceuses 18V et 5 boîtes de vis 4x40");

        $this->assertTrue($r->isComplete());
        $this->assertSame([
            ['query' => 'perceuses 18V', 'quantity' => 2.0, 'unit' => null],
            ['query' => 'vis 4x40', 'quantity' => 5.0, 'unit' => 'boîtes'],
        ], $r->lines);
    }

    public function test_quantity_after_product_and_one_article_per_line(): void
    {
        $r = $this->parse("Bonjour\n2x perceuse\nmarteau x3\nciment : 10 sacs\nmerci");

        $this->assertTrue($r->isComplete());
        $this->assertSame(['perceuse', 'marteau', 'ciment'], array_column($r->lines, 'query'));
        $this->assertSame([2.0, 3.0, 10.0], array_column($r->lines, 'quantity'));
        $this->assertSame('sacs', $r->lines[2]['unit']);
    }

    public function test_client_hint_on_first_line_and_number_words(): void
    {
        $r = $this->parse("Client : C0012\n3 rouleaux scotch\nune perceuse");

        $this->assertSame('C0012', $r->customerHint);
        $this->assertSame([3.0, 1.0], array_column($r->lines, 'quantity'));
        $this->assertSame(['scotch', 'perceuse'], array_column($r->lines, 'query'));
    }

    public function test_dimension_is_never_read_as_a_quantity(): void
    {
        foreach (['vis 4x40', '4x40 vis', 'perceuse 18V'] as $text) {
            $r = $this->parse($text);
            $this->assertSame([], $r->lines, $text);
            $this->assertSame([$text], $r->unparsed, $text);
        }
    }

    public function test_politeness_only_message_has_no_line_and_nothing_unparsed(): void
    {
        $r = $this->parse('ok merci');

        $this->assertSame([], $r->lines);
        $this->assertSame([], $r->unparsed);
        $this->assertFalse($r->isComplete());
    }

    public function test_phone_numbers_normalize_to_the_same_form(): void
    {
        foreach (['whatsapp:+212612345678', '06 12 34 56 78', '0612-345678', '00212612345678', '212612345678'] as $p) {
            $this->assertSame('+212612345678', PhoneNumber::normalize($p), $p);
        }
        $this->assertTrue(PhoneNumber::looksLikePhone('06 12 34 56 78'));
        $this->assertFalse(PhoneNumber::looksLikePhone('C0012'));
    }
}
