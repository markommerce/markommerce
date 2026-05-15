import postcssImport from 'postcss-import';
import postcssNesting from 'postcss-nesting';
import postcssCustomMedia from 'postcss-custom-media';
import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';

const isProduction = process.env.NODE_ENV === 'production';

/** @type {import('postcss').ProcessOptions} */
const config = {
  plugins: [
    postcssImport,
    postcssNesting,
    postcssCustomMedia,
    autoprefixer,
    ...(isProduction ? [cssnano({ preset: 'default' })] : []),
  ],
};

export default config;
