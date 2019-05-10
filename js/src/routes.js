import { getDeepProp } from './functions';

export function getRelUri(alias) {
  const rels = {
    'add': 'https://jsonapi.org/profiles/drupal/hypermedia/#add',
    'update': 'https://jsonapi.org/profiles/drupal/hypermedia/#update',
    'delete': 'https://jsonapi.org/profiles/drupal/hypermedia/#delete',
    'collection': 'collection',
  };
  return rels[alias];
}

export function getMethodsFromRel(rel) {
  const methods = {};
  methods[getRelUri('update')] = 'PATCH',
  methods[getRelUri('delete')] = 'DELETE'

  return Array.isArray(rel)
    ? rel.map(route => methods[route]).filter(route => route !== undefined)
    : methods[rel] ? [methods[rel]] : [];
}

export function objectHasLinkWithRel(obj, key, rel) {
  const rels = getDeepProp(obj, `links.${key}.meta.linkParams.rel`);
  return Array.isArray(rels) && rels.includes(rel);
}
