import React from 'react';
import { getDeepProp } from './functions';
import FluidComment from './FluidComment';

function renderThreaded(comments, refresh, isRefreshing) {

  const rendered = [];
  let stack = [];
  const destack = (stack, current) => {
    let item, last = null;
    while (item = stack.pop()) {
      if (last !== null) {
        item.children.push(last);
      }
      if (!current || !current.parentId || current.parentId !== item.id) {
        last = item.render(item.children);
      }
      else {
        stack.push(item);
        break;
      }
    }
    if (!current || !current.parentId) {
      rendered.push(last);
    }
    return stack;
  };
  for (var i = 0; i < comments.length; i++) {
    const comment = comments[i];
    const current = {
      id: comments[i].id,
      parentId: getDeepProp(comments[i], 'relationships.pid.data.id'),
      render: (children) => {
        return (
          <FluidComment
            key={comment.id}
            index={i}
            comment={comment}
            refresh={refresh}
            isRefreshing={isRefreshing}
          >
            {children}
          </FluidComment>
        );
      },
      children: [],
    };
    if (i > 0)  {
      stack = destack(stack, current);
    }
    stack.push(current);
  }
  destack(stack);
  return rendered;
}

function renderFlat(comments, refresh, isRefreshing) {

  return comments.map((comment, index) => (
    <FluidComment
      key={comment.id}
      index={index}
      comment={comment}
      refresh={refresh}
      isRefreshing={isRefreshing}
    />
  ));
}

const FluidCommentList = ({ threaded, comments, refresh, isRefreshing }) => (
  threaded
    ? renderThreaded(comments, refresh, isRefreshing)
    : renderFlat(comments, refresh, isRefreshing)
);

export default FluidCommentList;
