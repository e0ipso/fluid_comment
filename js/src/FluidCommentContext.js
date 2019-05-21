import React, { createContext } from 'react';

const FluidCommentContext = createContext({
  isRefreshing: false,
  filterDefaultFormat: 'restricted_html'
});

export default FluidCommentContext;
