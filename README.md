# horror-studio

Async LLM story pipeline: a REST API accepts a story request, answers immediately with `202 Accepted`,
and a Messenger worker generates the text in the background with retries, a failure transport and an
explicit status machine.

## Stack

PHP 8.3 · Symfony 7.4 · PostgreSQL 16 · RabbitMQ (Symfony Messenger, AMQP) · Docker · PHPUnit 12 · PHPStan (level max)

## Architecture

```
 client                  API (nginx + php-fpm)              RabbitMQ                 worker (messenger:consume)
   |                             |                              |                              |
   |  POST /api/stories          |                              |                              |
   |---------------------------->|  persist Story(pending)      |                              |
   |                             |  dispatch GenerateStoryMessage                              |
   |                             |----------------------------->|  queue "stories"             |
   |  202 + Location             |                              |----------------------------->|  pending -> generating
   |<----------------------------|                              |                              |  "LLM" call
   |                             |                              |                              |  generating -> completed
   |  GET /api/stories/{id}      |                              |                              |
   |---------------------------->|  read status / content       |                              |
   |<----------------------------|                              |                              |
                                                                                              |
                                        on exception: retry x3, delay 1s -> 2s -> 4s (+ jitter)
                                        retries exhausted: message moved to "failed" transport (Doctrine)
```

Status machine (enum `StoryStatus`, guarded by `canTransitionTo`):

```
pending ----> generating ----> completed
   |               |
   |               +---------> failed
   +-------------> cancelled
```

Any other transition throws `DomainException`, which the API maps to `409 Conflict`.

## Run

```bash
docker compose build && docker compose up -d && docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console messenger:consume async -vv
```

The API listens on `http://localhost:8080`, the RabbitMQ management UI on `http://localhost:15672` (guest/guest).

## Try it

Create a story. The response is `202`, the `Location` header points at the resource:

```bash
curl -i -X POST http://localhost:8080/api/stories \
  -H 'Content-Type: application/json' \
  -d '{"title":"Lighthouse","prompt":"The keeper went silent three nights ago"}'
```

```
HTTP/1.1 202 Accepted
Location: /api/stories/1

{"id":1,"status":"pending"}
```

Poll it. After the worker finishes (a few seconds), the status is `completed` and `content` is filled:

```bash
curl -s http://localhost:8080/api/stories/1
```

```json
{"id":1,"title":"Lighthouse","status":"completed","content":"== Lighthouse ==\n\nChapter 1.\nThe keeper went silent three nights ago... and the door creaked open.","created_at":"2026-09-14T10:15:02+00:00"}
```

Cancel a story. The first call on a `pending` story succeeds; a second call is an illegal transition and returns `409`:

```bash
curl -s -X POST http://localhost:8080/api/stories/2/cancel
curl -i -X POST http://localhost:8080/api/stories/2/cancel
```

```
{"id":2,"status":"cancelled"}

HTTP/1.1 409 Conflict
{"error":"Illegal transition cancelled -> cancelled"}
```

Other errors use the same JSON shape: an unknown id returns `404 {"error":"story not found"}`, an invalid payload
returns `422` with a `violations` list (`title` is required, max 200 chars; `prompt` is required, min 10 chars).

## Engineering decisions

- **Enum status machine with a transition guard.** `StoryStatus` is a backed enum stored via Doctrine `enumType`.
  The only way to change a status is `Story::transitionTo()`, which consults `canTransitionTo()` and throws on
  anything not in the diagram above. Terminal states (`completed`, `failed`, `cancelled`) have no outgoing edges.
- **Re-entrant consumer, safe under redelivery.** The handler re-reads the story and its status on every delivery.
  A cancelled or already-finished story is skipped and logged. A story already in `generating` (the worker was
  killed mid-run and the message was redelivered) continues without a second transition, so redelivery never
  hits the guard.
- **Retries and a failure transport.** The `async` transport retries 3 times
  with exponential backoff (1s, 2s, 4s, Messenger's default jitter applied).
  A transient error ("LLM flaked") is absorbed by a retry; a permanent one
  (a prompt containing `poison`) exhausts the retries and the message lands
  in the Doctrine-backed `failed` transport, where it can be inspected and
  replayed with `messenger:failed:*`. A `WorkerMessageFailedEvent` listener
  ignores deliveries that will still be retried (`willRetry()`) and, on the
  final rejection, transitions the story itself from `generating` to `failed`,
  so the API status always reflects reality.
- **Unified JSON errors.** A `kernel.exception` subscriber turns every exception into `{"error": ...}`:
  `DomainException` becomes `409`, HTTP exceptions keep their code and headers, validation failures become
  `422` with a `violations` array, and anything else is `500` with the real message only in `dev`.
- **Mocks vs stubs in tests.** Collaborators are mocked only when the interaction is the thing under test
  (`persist`/`flush` called once, `dispatch` receives the right id, `flush` never called on a 404).
  Where a collaborator only supplies state, it is a stub. Tests run without a database or broker.
- **The LLM is simulated.** The handler sleeps, fails randomly at a fixed rate and rejects a poison prompt.
  This is deliberate: it exercises retries, redelivery and the failure path without an external dependency.

## Roadmap

1. Transactional Outbox, so the message is committed together with the story row instead of dispatched after `flush()`.
2. Prometheus metrics: queue depth, generation latency, retry and failure counters.
3. A real LLM provider behind an interface, with the current simulator kept as the test implementation.
