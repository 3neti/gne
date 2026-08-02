# Property Reservation Storyboard Report

The storyboard was derived from the approved acceptance flow after reviewing AES at `7acb8baaa7a1d9e56f80136f1e267d3a82113f0e` and x-change at `38279c117ee72459d6cfc25b47d602386b7faade`. `STORYBOARD_REFERENCE_REVIEW.md` records file-level provenance.

The canonical choreography is `docs/mvp/storyboards/property-reservation-mvp.yaml`: five acts and 25 ordered frames. Business frames bind to snapshots from the real validator, chain selector, lifecycle/document-set compiler, and—where resolvable—the x-document browser representation. Fictional Alicia Demonstration / LOT-DEMO-101 state is prepared outside canonical `business/`.

Generated output lives under `.gne/storyboards/property-reservation-mvp/`: manifest, one public login capture, 21 authenticated production-surface captures, three authenticated and explicitly labeled explanation captures, a `gne-storyboard-html/1.0` offline site (index, five act pages, 25 frame pages, CSS, copied captures, and deterministic asset manifest), a 26-page screenshot-bearing PDF printed from finalized HTML, narration, movie manifest, and report. One ephemeral fictional operator submits the real login form and retains the same session for all 24 protected frames; narrative personas do not claim separate role authorization. HTML, PDF, and movie use the same ordered finalized frame inventory. FFmpeg availability determines whether MP4 is generated or remains build-ready.

The earlier artifact defect came from generating a preliminary metadata-only PHP PDF before capture while a second browser path generated another PDF from pre-finalization data. The command now removes any stale PDF, captures and fingerprints every required frame, writes final HTML, and only then invokes Chromium PDF printing. Final output contains no `Capture: planned` text. The HTML asset manifest fingerprints ordered relative paths and bytes; Chromium PDF timestamps remain environment-dependent, so its print-source SHA-256 is also recorded.

Final local artifact checksums: manifest `43ca7a91a22fddadba65854cc95a59c1380d0bc6796b3eea6d0af20fd3f39f62`; PDF `c38588782e4fe42417554130940a976b1407459eac1be9a580192c148ebef98f`; narration `d214fa4cc671870f4b8c5fd6b788a643e25a9e641e704a9434d9263039ef3d10`; movie manifest `6d48ce94335d97958aa27bd8dd31d144ed505298a8d7bf5ce03f7f2114fa5c83`.

Capture ran on local macOS, Laravel Herd, `http://gne.test`, and Playwright Chromium. Authentication used an ephemeral account. No password, cookie, token, private email, or production data is recorded.

The storyboard observes but never determines lifecycle or mutates accepted evidence. Product proof comes from actual GNE and x-document routes; annotations are composed afterward and cannot masquerade as application screenshots. It does not enter x-document/x-document-laravel. Its definition, runner, state provider, and capture boundary preserve a future `3neti/x-storyboard` extraction seam.

Readiness: demonstrable MVP remains achieved; this local environment is operator-ready for a repeatable reconstructed demonstration. No target production environment was available, so target-environment and production readiness are not claimed.
