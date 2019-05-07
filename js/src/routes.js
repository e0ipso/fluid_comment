
export function getMethodsFromRel(rel) {
  const methods = {
    'https://jsonapi.org/profiles/drupal/hypermedia/#update': 'PATCH',
    'https://jsonapi.org/profiles/drupal/hypermedia/#delete': 'DELETE'
  };

  return Array.isArray(rel)
    ? rel.map(route => methods[route]).filter(route => route !== undefined)
    : methods[rel] ? [methods[rel]] : [];
}
