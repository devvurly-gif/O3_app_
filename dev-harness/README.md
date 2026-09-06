# Banc de rendu

Monte un ecran de l'application seul, sur des donnees fixes, sans backend ni
session. Sert a regarder un composant avant et apres l'avoir decoupe : un
`v-if` inverse ou une classe perdue ne se voient ni au `type-check` ni au
`build`, seulement a l'ecran.

```bash
npm run harness
```

Puis <http://localhost:5199>.

`@/services/http` y est remplace par [mocks/http.ts](mocks/http.ts), qui repond
depuis [fixtures.ts](fixtures.ts). Les ecrans montes ne savent pas qu'ils sont
sur un banc : ils appellent les memes endpoints, avec les memes formes de
reponse.

Ce dossier ne part pas dans le build de production — il a sa propre
configuration Vite et n'est reference par aucune entree de `vite.config.js`.
