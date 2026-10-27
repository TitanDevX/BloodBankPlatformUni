# A4 landscape — current ERD-aligned version

Use `high-level-class-diagram-a4.mmd` for the compact Mermaid source and `output/pdf/Blood_Bank_Class_Diagram_A4.pdf` for the verified print result. The main and horizontal Mermaid filenames are kept identical to the current overview, so previous links do not retain the old naming.

The overview preserves all 14 entity identifiers and all 22 association multiplicities from `docs/ER/code/erd-low-level.mmd`. It is a high-level UML presentation: selected attribute names and public operations are displayed; attribute and return types are suppressed for readability. Every attribute shown uses the ERD identifier verbatim. No alias classes or replacement value objects are used.

`class-diagram-detailed.mmd` contains all 135 ERD attributes with their exact data types, all 22 relationship captions, and key/nullability annotations. It is a detailed reference model, not the one-page print layout.

The final print is one 297 × 210 mm landscape page with 6 mm margins, around 8.59 pt vector diagram text, an overview title and a scope note. All exact class names are searchable in the PDF. The final render has zero detected connector crossings and zero detected paths through class boxes; visual inspection found no clipping, unwanted class-name wrapping or text crossing compartment borders. Print at actual size / 100%.

Tested using Mermaid 12.2.0, ELK, plain SVG labels and Arial Narrow. Matching global/theme font settings keep text measurement consistent. Other renderers/fonts may change automatic placement; use the PDF for the checked print layout. The layout is compact among checked candidates; no claim of mathematical minimum area is made.
