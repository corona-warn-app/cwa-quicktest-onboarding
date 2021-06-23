
Das Namensschema für dieses Verzeichnis lautet:  
`[testId].json`

Der Simulator prüft für jeden DCC-Antrag, ob im Verzeichnis `testresults` 
eine Datei mit dem Namen der Test-ID und der Erweiterung `.json` existiert.
Wenn ja, wird der Inhalt dieser Datei als Basis für das DCC verwendet. 
Ansonsten wird ein zufälliges DCC (zufälliger Name, Geburtsdatum, etc.)
generiert. 

