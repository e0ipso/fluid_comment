import { getDeepProp } from './functions';

const rels = {
  'https://jsonapi.org/profiles/drupal/hypermedia/#add': {
    alias: 'add',
    method: 'POST'
  },
  'https://jsonapi.org/profiles/drupal/hypermedia/#update': {
    alias: 'update',
    method: 'PATCH'
  },
  'https://jsonapi.org/profiles/drupal/hypermedia/#delete': {
    alias: 'delete',
    method: 'DELETE'
  },
  'collection': {
    alias: 'collection',
    method: 'GET'
  }
};

export const getRelUri = (alias) => (
  Object.keys(rels).find(uri => rels[uri].alias === alias)
);

export const getMetaFromRel = (rel) => (
  Array.isArray(rel)
    ? rel.filter(rel => rels[rel] !== undefined).map(rel => rels[rel])
    : rels[rel] ? [rels[rel]] : []
);


export function objectHasLinkWithRel(obj, key, rel) {
  const rels = getDeepProp(obj, `links.${key}.meta.linkParams.rel`);
  return Array.isArray(rels) && rels.includes(rel);
}
