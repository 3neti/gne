# Open Questions for the Anaesthesia Department

These questions do not block all foundation work. Until answered, implementations must preserve explicit inputs, avoid legacy-code automation, and keep affected decisions reversible.

## Contracts, hours, and assignment identity

1. What period does `80 Hours` represent: week, fortnight, four-week roster, or another contract interval?
2. What period does `40 Hours` represent?
3. How do `0.75FTE` and `0.5FTE` relate to contracted hours and roster-period targets?
4. Does a dated `5 Hours` entry represent credited hours, required hours, a partial duty, or something else?
5. Are morning and afternoon rows one daily assignment or two separate assignments?
6. Can a doctor have multiple independent duties on one date?
7. Which start/end times are standard defaults, and when may they be overridden?
8. Is the roster fundamentally weekly, fortnightly, four-weekly, or governed by several concurrent periods?

## Call, overtime, and recovery

9. What constitutes `ONC_STD ONCALL`?
10. How many hours are credited for on-call?
11. Does 24-hour on-call count toward ordinary required hours, and if so how?
12. What constitutes `2nd Call`, and can it coexist with another duty?
13. What is the difference between `overtime` and `PLAN_OT`?
14. What does `st-down` mean operationally?
15. What does `Post Nights` mean, and does it prohibit ordinary assignment?

## Leave, education, and availability

16. What do `REC`, `LSL`, `PDL`, `R/L`, and `DCT` mean operationally?
17. Which codes are hard prohibitions on assignment?
18. Which codes are soft scheduling preferences?
19. Which codes represent paid work away from ordinary clinical duties?
20. Which codes merely annotate an existing assignment?
21. How should teaching, examinations, CME, meetings, workshops, and committee commitments affect availability and credited hours?
22. Does “no calls if possible” have an approval process or priority relative to other preferences?

## Calendar and staffing

23. What do the numeric values in the final row represent: required doctors, assigned doctors, theatre demand, vacancies, or another metric?
24. Why are final-row numbers shown for selected weekdays but not weekends?
25. Are weekends staffed or credited differently?
26. Is `Ekka` a public-holiday marker for this roster, and are public holidays staffed or credited differently?
27. Are there minimum requirements by seniority, staff group, theatre, or call role in addition to a total doctor count?

## Identity, references, publication, and governance

28. Which adjacent numeric values are employee numbers, and are leading zeroes significant?
29. What are `PN...` and `PR...` references?
30. Are `SMO/VMO`, `MEDREG`, `Provisional Fellow`, and `ED Reg` scheduling classifications that affect rules?
31. Is publication currently equivalent to distributing this spreadsheet?
32. Who is authorised to create, change, approve, and publish the roster?
33. Which roster changes require an explanation or approval?
34. Which changes must be notified to affected doctors?
35. Which source data, decisions, prior revisions, and approvals must be retained for audit?
36. Which part of the present workbook causes the most operational difficulty or risk?
37. Which reports or downstream processes rely on its colours, layout, codes, or exact file format?

## Safe interim assumptions

- Roster-period required hours will be entered explicitly.
- Standard daily credited hours will be explicit per doctor.
- One counted daily assignment per doctor/date is the MVP limit.
- Assignment duty code and start/end/credited hours remain optional extension fields.
- Application-authored `leave` and `unavailable` are hard prohibitions.
- No legacy code is imported or activated until confirmed.
- Daily staffing requirement is administrator-authored, not taken from the workbook's final row.
