<?php

namespace App\Http\Controllers\AI;

use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\KnowledgeGraph\TraversalSpec;
use App\Domain\Ontology\EntityDefinition;
use App\Domain\Ontology\EntityResolver;
use App\Domain\Ontology\OntologyRegistry;
use App\Domain\Ontology\RelationshipDefinition;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Ontology and Knowledge Graph read APIs.
 *
 * The entity and relationship listings are metadata — the shape of the model, not
 * anyone's data — so they are safe for any authenticated user. `resolve` and `query`
 * return records, and both go through the tenant-scoped resolver and graph service.
 *
 * Traversal is bounded by TraversalSpec, which rejects an over-deep or over-wide
 * request rather than trying to serve it.
 */
class OntologyController extends AiController
{
    public function __construct(
        private readonly OntologyRegistry $registry,
        private readonly EntityResolver $resolver,
        private readonly GraphQueryService $graph,
    ) {
    }

    public function entities(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $domain = $request->input('domain');

            $entities = $domain
                ? $this->registry->entitiesInDomain($domain, $scope->selectedInstituteId)
                : array_values($this->registry->entities($scope->selectedInstituteId));

            return $this->success('Ontology entities loaded.', [
                'entities' => array_map(
                    fn (EntityDefinition $entity) => $entity->toArray(),
                    $entities
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function relationships(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $relationships = $request->filled('from')
                ? $this->registry->relationshipsFrom($request->input('from'), $scope->selectedInstituteId)
                : $this->registry->relationships($scope->selectedInstituteId);

            return $this->success('Ontology relationships loaded.', [
                'relationships' => array_map(
                    fn (RelationshipDefinition $relationship) => $relationship->toArray(),
                    $relationships
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Resolve an entity mention to real records — the lookup the conversational
     * layer uses to turn "Riya in 8B" into a student id.
     */
    public function resolve(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'entity' => 'required|string|max:100',
                'search' => 'nullable|string|max:200',
                'limit' => 'nullable|integer|min:1|max:200',
            ]);

            return $this->success('Entities resolved.', [
                'entity' => $validated['entity'],
                'results' => $this->resolver->resolve(
                    $validated['entity'],
                    $scope,
                    $validated['search'] ?? null,
                    $this->limit($request, 25)
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Which entities might the user's words be about? Used for intent resolution.
     */
    public function candidates(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate(['text' => 'required|string|max:500']);

            return $this->success('Candidate entities resolved.', [
                'candidates' => $this->resolver->candidateEntities(
                    $validated['text'],
                    $scope->selectedInstituteId
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Walk the graph. This is what answers "why is this student at risk?" as a
     * traversal rather than as a paragraph.
     */
    public function query(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'entity' => 'required|string|max:100',
                'id' => 'required',
                'path' => 'nullable|array|max:6',
                'path.*' => 'string|max:100',
                'relations' => 'nullable|array|max:6',
                'relations.*' => 'string|max:80',
                'max_depth' => 'nullable|integer|min:1|max:6',
                'limit' => 'nullable|integer|min:1|max:200',
                'prefer_graph' => 'nullable|boolean',
            ]);

            $spec = TraversalSpec::fromArray($validated);

            return $this->success('Graph traversal complete.', $this->graph->traverse($spec, $scope));
        } catch (InvalidArgumentException $exception) {
            // A bad traversal request is the caller's error, and the message is safe.
            return $this->failure($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * What can be walked from here — so a caller can discover the graph rather than
     * guess at relation names.
     */
    public function relations(Request $request, string $entity)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Available relations loaded.', [
                'entity' => $entity,
                'relations' => $this->graph->availableRelations($entity, $scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
