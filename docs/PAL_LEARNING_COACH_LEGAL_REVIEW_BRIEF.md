# Learning Coach observation — brief for legal review

Prepared 2026-09-11 for tracker item **#31**, which states that *any* screen-observation or camera-based capability requires legal review under India's DPDP Act **before any engineering discussion**.

**This document does not answer the legal questions and is not legal advice.** It exists so the review can happen against a concrete proposal rather than a vague one. Everything below is either a statement of what the product would do, or a question for counsel.

---

## 1. What is actually being proposed

The tracker records a **correction** that matters to this review:

- Alpha School / TimeBack's **publicly stated** design is **screen-share / on-device data only**.
- **Camera-based observation was NOT verified** as an existing competitor practice. It was an assumption in the original discussion, and it does not have a documented precedent behind it.

These are materially different capabilities and should not be reviewed as one:

| | Capability A — screen/on-device | Capability B — camera |
|---|---|---|
| Captures | What is on the learner's own screen, and interaction telemetry | Live image of the learner, in their home or classroom |
| Precedent | Publicly described by a comparable product | **None verified** |
| Third parties captured | No | **Yes** — anyone in frame, including other children and adults who are not users |

**Recommendation to the reviewer: treat B as a separate submission.** Approving A should not imply B, and the case for B does not currently rest on evidence.

---

## 2. Who the data subjects are

**Children.** This is a K-12 product; the learners are minors, and a substantial share are under 13.

Under the DPDP Act this is the governing fact, not a detail — the obligations that attach to a child Data Principal are stricter than the general regime, and several general-purpose lawful bases are not available.

---

## 3. Questions for counsel

These are the decisions engineering cannot make.

**Consent and guardianship**
1. Whose consent is required — the parent/guardian's, the school's as an institution, or both? Does a school's enrolment agreement cover this, or does it require separate, specific, verifiable parental consent?
2. Is consent freely given if the alternative is not using the learning platform the school has mandated? Can a learner decline observation and still use PAL?
3. How is verifiable parental consent obtained and recorded, and what happens to already-collected data if it is later withdrawn?

**Purpose limitation and minimisation**
4. Observation for *pedagogical support* is a narrower purpose than observation for *proctoring or integrity*. May the same capture serve both, or must they be separately consented?
5. What is the minimum data that achieves the purpose — raw capture, or derived signals only (e.g. "idle for N minutes", "switched away from the task")? Is raw capture defensible where a derived signal would do?

**Tracking and behavioural monitoring**
6. The DPDP Act restricts tracking and behavioural monitoring of children. Does continuous or sampled screen observation constitute behavioural monitoring as contemplated there? This is the question most likely to be decisive.

**Retention, access and residency**
7. How long may observation data be retained, and what triggers erasure — end of term, end of enrolment, request?
8. Who inside a school may view it — the class teacher, any teacher, administrators? Can a parent access what was captured about their child?
9. Are there data-residency or cross-border constraints if processing involves a model or service hosted outside India?

**Capability B specifically**
10. Camera capture records people who are not users and have not consented — siblings, parents, classmates. Is that lawful at all in a home setting, and what would make it so?

---

## 4. What engineering would need, whichever way it goes

Stated so the reviewer knows what a "yes" commits the product to building:

- a consent record per learner, with grant/withdraw timestamps and the scope consented to
- a hard technical gate: **no capture without a current, valid consent record**
- a retention clock and automated erasure, not a manual process
- an access-control model for who may view captured data, and an audit trail of who did
- a learner-visible indicator that observation is active — arguably required, and in any case the right default for a product used by children

None of this is built. None of it should be built before this review concludes.

---

## 5. The product position, which is not in question

The tracker records a philosophy that should frame the review regardless of outcome:

> the system must support **the learning interaction** — not **"AI watches the child"**.

If a capability cannot be described to a parent in those terms, that is a signal about the capability, not about the wording.
