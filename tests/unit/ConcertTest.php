<?php

use App\Concert;
use Carbon\Carbon;
use App\Exceptions\NotEnoughTicketsRemainException;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConcertTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    function can_get_formatted_date()
    {
        $concert = factory(Concert::class)->make([
            'date' => Carbon::parse('2016-12-01 8:00pm'),
        ]);

        $this->assertEquals('December 1, 2016', $concert->formatted_date);
    }

    /** @test */
    function can_get_formatted_start_time()
    {
        $concert = factory(Concert::class)->make([
            'date' => Carbon::parse('2016-12-01 17:00:00'),
        ]);

        $this->assertEquals('5:00pm', $concert->formatted_start_time);
    }

    /** @test */
    function can_get_ticket_price_in_dollars()
    {
        $concert = factory(Concert::class)->make([
            'ticket_price' => 6750,
        ]);

        $this->assertEquals('67.50', $concert->ticket_price_in_dollars);
    }

    /** @test */
    function concerts_with_a_published_at_date_are_published()
    {
        $publishedConcertA = factory(Concert::class)->create(['published_at' => Carbon::parse('-1 week')]);
        $publishedConcertB = factory(Concert::class)->create(['published_at' => Carbon::parse('-1 week')]);
        $unpublishedConcert = factory(Concert::class)->create(['published_at' => null]);

        $publishedConcerts = Concert::published()->get();

        $this->assertTrue($publishedConcerts->contains($publishedConcertA));
        $this->assertTrue($publishedConcerts->contains($publishedConcertB));
        $this->assertFalse($publishedConcerts->contains($unpublishedConcert));
    }

    /** @test */
    public function can_order_concert_tickers()
    {
        $concert = create(Concert::class);
        $concert->addTickets(3);

        $order = $concert->orderTickets('jane@example.com', 3);

        $this->assertEquals('jane@example.com', $order->email);
        $this->assertEquals(3, $order->tickets()->count());
    }

    /** @test */
    public function can_add_tickets()
    {
        $concert = create(Concert::class);

        $concert->addTickets(5);

        $this->assertEquals(5, $concert->ticketsRemaining());
    }

    /** @test */
    public function tickets_remaining_does_not_includes_tickets_that_were_already_allocated()
    {
        $concert = create(Concert::class);
        $concert->addTickets(10);
        $concert->orderTickets('joey.tribbiani@daysofourlives.tv', 4);

        $this->assertEquals(6, $concert->ticketsRemaining());
    }

    /** @test */
    public function trying_to_purchase_more_tickets_than_remain_throws_an_exception()
    {
        $concert = factory(Concert::class)->create();
        $concert->addTickets(10);

        try {
            $concert->orderTickets('holly@thedog.com', 24);
        } catch (NotEnoughTicketsRemainException $e) {
            $this->assertNull($concert->orders()->where('email', 'holly@thedog.com')->first());
            $this->assertEquals(10, $concert->ticketsRemaining());
            return;
        }

        $this->fail('The order suceeded even though there wasn\'t enough tickets left to fulfill it.');
    }

    /** @test */
    public function cannot_purchase_tickets_that_have_already_been_purchased()
    {
        $concert = factory(Concert::class)->create();
        $concert->addTickets(25);
        $concert->orderTickets('holly@thedog.com', 21);

        try {
            $concert->orderTickets('duchess@thedog.com', 7);
        } catch (NotEnoughTicketsRemainException $e) {
            $this->assertNull($concert->orders()->where('email', 'duchess@thedog.com')->first());
            $this->assertEquals(4, $concert->ticketsRemaining());
            return;
        }

        $this->fail('The order suceeded even though there wasn\'t enough tickets left to fulfill it.');
    }
}
