Indexador
=========

Script php de indexación de temas para SMF

------

**Modulo:** Script Indexador

**Descripción:** Indexar los posts, con TAGs.

**Entrada:**

* **f=(##)** // número del foro o subforo de SMF
* **t=(1:(COM) o 2: (PED))** // tag a buscar [t es un parámetro opcional, por defecto 1]
* **l=(letra)** // indexar solo la letra indicada [l es un parámetro opcional]
* **a** // lista incluyendo el año antes del nombre [a es un parámetro opcional]

**Ejemplos:**
```
http://www.*tuweb*.com.ar/indexar.php?f=5&t=1&l=d&a
$ php indexar.php --f=5 --t=1 --l=d -a
```
