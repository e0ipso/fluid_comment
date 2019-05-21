import React from 'react';

const FluidCommentAuthor = ({ author }) => (
  <>
    {(author.image && author.url) &&
      <article
        className="contextual-region profile"
        typeof="schema:Person"
        about={author.url}
      >
        <div className="field field--type-image">
          <a href={author.url}>
            <img
              src={author.image}
              width="100"
              height="100"
              alt={`Profile picture for user ${author.name}`}
              typeof="foaf:Image"
              className="image-style-thumbnail"
            />
          </a>
        </div>
      </article>
    }
    <p className="comment__author">
      {author.url
        ? <a
            title="View user profile."
            href={author.url}
            about={author.url}
            typeof="schema:Person"
            property="schema:name"
            className="username"
          >
            {author.name}
          </a>
        : author.name
      }
    </p>
  </>
);

export default FluidCommentAuthor;
