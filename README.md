# bugbyte-solver

This is an object-oriented PHP-based solution for the problem bugbyte from janestreet.

Before I got it all right, the code would run for *very* long periods of time without getting the answer.
under the current constraints, it resolves to the answer in 50 seconds.

This problem was sufficiently interesting that I've spent quite a bit of time on it.

How it works:
1. it uses constants, range limits, equations, and constraints.
2. It uses constants for values that can be deduced algebraically from the data. e.g., the provided values of (12,24,7,20), the top three entries and the entry "I" directly under the three paths 6,9,16.
3. Range limits are used to reduce the total search space. e.g. the first element after a path that starts with 8 or 6 cannot be greater than 8 or 6, because that would make the path impossible.
4. the equations are used to limit the possible values for each square. By using the known constants, we can eliminate possible values (no square can have a value already taken regardless of whether it would work).
5. The equation also enable us to avoid searching for the final term in each equation. The final part of an equation can also be resolved simply by remainder (if A + B + C = 12 and A is 5 and B = 4 ... well C = 3).
6. the constraints then follow paths. Here, I'm not using an intelligent path finder as nothing that complicated occurs. Instead, I manually composed the paths by making a string of variables for each possible answer.
7. It then loops through every possible value that it thinks can work on the independent variables. When something works for all independent variables, it then attempts to set the dependent variables. If it succeeds, it checks the constraints.





